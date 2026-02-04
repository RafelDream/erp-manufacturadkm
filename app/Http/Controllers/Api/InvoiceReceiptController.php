<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InvoiceReceipt;
use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InvoiceReceiptController extends Controller
{
    /**
     * GET - List Tanda Terima Faktur
     */
    public function index(Request $request)
    {
        $query = InvoiceReceipt::with([
            'purchaseOrder.supplier',
            'invoices',
            'creator',
            'requester'
        ]);

        // Filter by date range
        if ($request->start_date && $request->end_date) {
            $query->whereBetween('transaction_date', [$request->start_date, $request->end_date]);
        }

        // Filter by status
        if ($request->status) {
            $query->where('status', $request->status);
        }

        // Filter by supplier
        if ($request->supplier_id) {
            $query->whereHas('purchaseOrder', function ($q) use ($request) {
                $q->where('supplier_id', $request->supplier_id);
            });
        }

        // Filter by requester
        if ($request->requester_id) {
            $query->where('requester_id', $request->requester_id);
        }

        return response()->json($query->latest()->get());
    }

    /**
     * GET - Detail Tanda Terima Faktur
     */
    public function show($id)
    {
        return response()->json(
            InvoiceReceipt::with([
                'purchaseOrder.supplier',
                'purchaseOrder.items.rawMaterial',
                'purchaseOrder.items.product',
                'invoices',
                'creator',
                'requester'
            ])->findOrFail($id)
        );
    }

    /**
     * POST - Create Tanda Terima Faktur (Draft)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'transaction_date' => 'required|date',
            'requester_id' => 'required|exists:users,id',
            'notes' => 'nullable|string',
        ]);

        // Validasi PO harus sudah sent atau received
        $po = PurchaseOrder::with('supplier')->findOrFail($validated['purchase_order_id']);
        
        if (!in_array($po->status, ['sent', 'received', 'closed'])) {
            return response()->json([
                'message' => 'Hanya PO yang sudah dikirim yang bisa dibuat tanda terima faktur'
            ], 422);
        }

        try {
            $receipt = DB::transaction(function () use ($validated, $po) {
                // Create Invoice Receipt
                $receipt = InvoiceReceipt::create([
                    'receipt_number' => 'TTF-' . now()->format('YmdHis'),
                    'purchase_order_id' => $po->id,
                    'transaction_date' => $validated['transaction_date'],
                    'requester_id' => $validated['requester_id'],
                    'supplier_id' => $po->supplier_id,
                    'status' => 'draft',
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => Auth::id(),
                ]);

                return $receipt;
            });

            return response()->json([
                'message' => 'Tanda Terima Faktur berhasil dibuat (draft)',
                'data' => $receipt->load('purchaseOrder.supplier', 'requester')
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal membuat Tanda Terima Faktur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT - Update Tanda Terima Faktur (only draft)
     */
    public function update(Request $request, $id)
    {
        $receipt = InvoiceReceipt::findOrFail($id);

        if ($receipt->status !== 'draft') {
            return response()->json([
                'message' => 'Hanya tanda terima draft yang bisa diupdate'
            ], 422);
        }

        $validated = $request->validate([
            'transaction_date' => 'required|date',
            'requester_id' => 'required|exists:users,id',
            'notes' => 'nullable|string',
        ]);

        $receipt->update($validated);

        return response()->json([
            'message' => 'Tanda Terima Faktur berhasil diupdate'
        ]);
    }

    /**
     * POST - Add Invoice to Receipt
     */
    public function addInvoice(Request $request, $id)
    {
        $receipt = InvoiceReceipt::findOrFail($id);

        if ($receipt->status !== 'draft') {
            return response()->json([
                'message' => 'Hanya tanda terima draft yang bisa ditambah faktur'
            ], 422);
        }

        $validated = $request->validate([
            'invoice_number' => 'required|string',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        try {
            $invoice = $receipt->invoices()->create([
                'invoice_number' => $validated['invoice_number'],
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'],
                'amount' => $validated['amount'],
                'notes' => $validated['notes'] ?? null,
            ]);

            return response()->json([
                'message' => 'Faktur berhasil ditambahkan',
                'data' => $invoice
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menambahkan faktur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE - Remove Invoice from Receipt
     */
    public function removeInvoice($receiptId, $invoiceId)
    {
        $receipt = InvoiceReceipt::findOrFail($receiptId);

        if ($receipt->status !== 'draft') {
            return response()->json([
                'message' => 'Hanya tanda terima draft yang bisa dihapus fakturnya'
            ], 422);
        }

        $invoice = $receipt->invoices()->findOrFail($invoiceId);
        $invoice->delete();

        return response()->json([
            'message' => 'Faktur berhasil dihapus'
        ]);
    }

    /**
     * PUT - Update Invoice
     */
    public function updateInvoice(Request $request, $receiptId, $invoiceId)
    {
        $receipt = InvoiceReceipt::findOrFail($receiptId);

        if ($receipt->status !== 'draft') {
            return response()->json([
                'message' => 'Hanya tanda terima draft yang bisa diupdate fakturnya'
            ], 422);
        }

        $invoice = $receipt->invoices()->findOrFail($invoiceId);

        $validated = $request->validate([
            'invoice_number' => 'required|string',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $invoice->update($validated);

        return response()->json([
            'message' => 'Faktur berhasil diupdate',
            'data' => $invoice
        ]);
    }

    /**
     * POST - Submit Tanda Terima (finalize)
     */
    public function submit($id)
    {
        $receipt = InvoiceReceipt::with('invoices')->findOrFail($id);

        if ($receipt->status !== 'draft') {
            return response()->json([
                'message' => 'Hanya tanda terima draft yang bisa disubmit'
            ], 422);
        }

        // Validasi: harus ada minimal 1 invoice
        if ($receipt->invoices->count() === 0) {
            return response()->json([
                'message' => 'Tanda terima harus memiliki minimal 1 faktur'
            ], 422);
        }

        $receipt->update([
            'status' => 'submitted',
            'submitted_by' => Auth::id(),
            'submitted_at' => now(),
        ]);

        return response()->json([
            'message' => 'Tanda Terima Faktur berhasil disubmit'
        ]);
    }

    /**
     * POST - Approve Tanda Terima
     */
    public function approve($id)
    {
        $receipt = InvoiceReceipt::findOrFail($id);

        if ($receipt->status !== 'submitted') {
            return response()->json([
                'message' => 'Hanya tanda terima submitted yang bisa diapprove'
            ], 422);
        }

        $receipt->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Tanda Terima Faktur berhasil diapprove'
        ]);
    }

    /**
     * POST - Reject Tanda Terima
     */
    public function reject($id)
    {
        $receipt = InvoiceReceipt::findOrFail($id);

        if ($receipt->status !== 'submitted') {
            return response()->json([
                'message' => 'Hanya tanda terima submitted yang bisa direject'
            ], 422);
        }

        $receipt->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Tanda Terima Faktur ditolak'
        ]);
    }

    /**
     * DELETE - Soft delete Tanda Terima
     */
    public function destroy($id)
    {
        $receipt = InvoiceReceipt::findOrFail($id);

        if (in_array($receipt->status, ['approved'])) {
            return response()->json([
                'message' => 'Tanda terima yang sudah approved tidak bisa dihapus'
            ], 422);
        }

        $receipt->delete();

        return response()->json([
            'message' => 'Tanda Terima Faktur berhasil dihapus'
        ]);
    }

    /**
     * POST - Restore Tanda Terima
     */
    public function restore($id)
    {
        InvoiceReceipt::withTrashed()->findOrFail($id)->restore();

        return response()->json([
            'message' => 'Tanda Terima Faktur berhasil direstore'
        ]);
    }

    /**
     * GET - Daftar PO yang bisa dibuat tanda terima
     */
    public function getEligiblePurchaseOrders()
    {
        $pos = PurchaseOrder::with('supplier', 'items.rawMaterial', 'items.product')
            ->whereIn('status', ['sent', 'received', 'closed'])
            ->latest()
            ->get();

        return response()->json($pos);
    }

    /**
     * GET - Summary total amount
     */
    public function getSummary($id)
    {
        $receipt = InvoiceReceipt::with('invoices')->findOrFail($id);

        $totalAmount = $receipt->invoices->sum('amount');
        $invoiceCount = $receipt->invoices->count();

        return response()->json([
            'receipt_number' => $receipt->receipt_number,
            'total_invoices' => $invoiceCount,
            'total_amount' => $totalAmount,
            'status' => $receipt->status,
        ]);
    }
}