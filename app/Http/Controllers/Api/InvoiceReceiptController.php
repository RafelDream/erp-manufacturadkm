<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
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

        if ($request->start_date && $request->end_date) {
            $query->whereBetween('transaction_date', [$request->start_date, $request->end_date]);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->supplier_id) {
            $query->whereHas('purchaseOrder', function ($q) use ($request) {
                $q->where('supplier_id', $request->supplier_id);
            });
        }

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
     * Amount invoice otomatis dihitung dari total subtotal PO items.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'transaction_date'  => 'required|date',
            'invoice_number'    => 'required|string',
            'invoice_date'      => 'required|date',
            'due_date'          => 'required|date|after_or_equal:invoice_date',
            'requester_id'      => 'required|exists:users,id',
            'notes'             => 'nullable|string',
        ]);

        $po = PurchaseOrder::with('items')->findOrFail($validated['purchase_order_id']);

        if (!in_array($po->status, ['sent', 'received', 'closed'])) {
            return response()->json([
                'message' => 'Hanya PO yang sudah dikirim yang bisa dibuat tanda terima faktur'
            ], 422);
        }

        // Validasi semua item PO sudah punya subtotal
        if ($po->items->whereNull('subtotal')->count() > 0) {
            return response()->json([
                'message' => 'Semua item PO harus memiliki harga sebelum membuat tanda terima faktur'
            ], 422);
        }

        // Amount = total subtotal dari semua PO items
        $totalAmount = $po->items->sum('subtotal');

        if ($totalAmount <= 0) {
            return response()->json([
                'message' => 'Total amount PO harus lebih dari 0'
            ], 422);
        }

        try {
            $receipt = DB::transaction(function () use ($validated, $po, $totalAmount) {
                $receipt = InvoiceReceipt::create([
                    'receipt_number'    => 'TTF-' . now()->format('YmdHis'),
                    'purchase_order_id' => $po->id,
                    'transaction_date'  => $validated['transaction_date'],
                    'requester_id'      => $validated['requester_id'],
                    'supplier_id'       => $po->supplier_id,
                    'status'            => 'draft',
                    'notes'             => $validated['notes'] ?? null,
                    'created_by'        => Auth::id(),
                ]);

                // Invoice dibuat otomatis — amount dari subtotal PO
                $receipt->invoices()->create([
                    'invoice_number' => $validated['invoice_number'],
                    'invoice_date'   => $validated['invoice_date'],
                    'due_date'       => $validated['due_date'],
                    'amount'         => $totalAmount,
                    'notes'          => $validated['notes'] ?? null,
                ]);

                return $receipt;
            });

            return response()->json([
                'message' => 'Tanda Terima Faktur berhasil dibuat (draft)',
                'data'    => $receipt->load('purchaseOrder.supplier', 'invoices', 'requester')
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal membuat Tanda Terima Faktur',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT - Update Tanda Terima Faktur (only draft)
     * Amount tidak bisa diubah manual — selalu mengikuti subtotal PO.
     */
    public function update(Request $request, $id)
    {
        $receipt = InvoiceReceipt::with('invoices', 'purchaseOrder.items')->findOrFail($id);

        if ($receipt->status !== 'draft') {
            return response()->json([
                'message' => 'Hanya tanda terima draft yang bisa diupdate'
            ], 422);
        }

        $validated = $request->validate([
            'transaction_date' => 'required|date',
            'invoice_number'   => 'required|string',
            'invoice_date'     => 'required|date',
            'due_date'         => 'required|date|after_or_equal:invoice_date',
            'requester_id'     => 'required|exists:users,id',
            'notes'            => 'nullable|string',
        ]);

        // Recalculate amount dari PO (single source of truth)
        $totalAmount = $receipt->purchaseOrder->items->sum('subtotal');

        $receipt->update([
            'transaction_date' => $validated['transaction_date'],
            'requester_id'     => $validated['requester_id'],
            'notes'            => $validated['notes'] ?? null,
        ]);

        // Update invoice (selalu 1 invoice per receipt)
        $invoice = $receipt->invoices()->first();
        if ($invoice) {
            $invoice->update([
                'invoice_number' => $validated['invoice_number'],
                'invoice_date'   => $validated['invoice_date'],
                'due_date'       => $validated['due_date'],
                'amount'         => $totalAmount,
                'notes'          => $validated['notes'] ?? null,
            ]);
        }

        return response()->json([
            'message' => 'Tanda Terima Faktur berhasil diupdate',
            'data'    => $receipt->load('purchaseOrder.supplier', 'invoices', 'requester')
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

        if ($receipt->invoices->count() === 0) {
            return response()->json([
                'message' => 'Tanda terima harus memiliki faktur'
            ], 422);
        }

        $receipt->update([
            'status'       => 'submitted',
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
            'status'      => 'approved',
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
            'status'      => 'rejected',
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

        if ($receipt->status === 'approved') {
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
     * Sudah include total_amount dari subtotal items
     */
    public function getEligiblePurchaseOrders()
    {
        $pos = PurchaseOrder::with('supplier', 'items.rawMaterial', 'items.product')
            ->whereIn('status', ['sent', 'received', 'closed'])
            ->latest()
            ->get()
            ->map(function ($po) {
                $po->total_amount = $po->items->sum('subtotal');
                return $po;
            });

        return response()->json($pos);
    }

    /**
     * GET - Summary total amount
     */
    public function getSummary($id)
    {
        $receipt = InvoiceReceipt::with('invoices')->findOrFail($id);

        $invoice = $receipt->invoices()->first();

        return response()->json([
            'receipt_number' => $receipt->receipt_number,
            'total_invoices' => $receipt->invoices->count(),
            'total_amount'   => $invoice?->amount ?? 0,
            'status'         => $receipt->status,
        ]);
    }

    /**
     * GET - Data lengkap untuk cetak invoice
     * Berisi info receipt, supplier, list barang PO beserta harga & subtotal.
     */
    public function print($id)
    {
        $receipt = InvoiceReceipt::with([
            'purchaseOrder.supplier',
            'purchaseOrder.items.rawMaterial',
            'purchaseOrder.items.product',
            'purchaseOrder.items.unit',
            'invoices',
            'requester',
            'creator',
            'approver',
        ])->findOrFail($id);

        $invoice  = $receipt->invoices->first();
        $po       = $receipt->purchaseOrder;
        $supplier = $po->supplier;

        // Susun list barang dari PO items
        $items = $po->items->map(function ($item) {
            $name = $item->rawMaterial?->name ?? $item->product?->name ?? '-';
            $code = $item->rawMaterial?->code ?? $item->product?->code ?? '-';
            $unit = $item->unit?->name ?? '-';

            return [
                'code'     => $code,
                'name'     => $name,
                'unit'     => $unit,
                'quantity' => (float) $item->quantity,
                'price'    => (float) $item->price,
                'subtotal' => (float) $item->subtotal,
            ];
        });

        $grandTotal = $items->sum('subtotal');

        return response()->json([
            // ── Header Invoice ──────────────────────────────────────
            'invoice' => [
                'invoice_number'  => $invoice?->invoice_number,
                'invoice_date'    => $invoice?->invoice_date,
                'due_date'        => $invoice?->due_date,
                'amount'          => (float) ($invoice?->amount ?? 0),
            ],

            // ── Tanda Terima Faktur ─────────────────────────────────
            'receipt' => [
                'receipt_number'   => $receipt->receipt_number,
                'transaction_date' => $receipt->transaction_date,
                'status'           => $receipt->status,
                'notes'            => $receipt->notes,
                'requester'        => $receipt->requester?->name,
                'created_by'       => $receipt->creator?->name,
                'approved_by'      => $receipt->approver?->name,
                'approved_at'      => $receipt->approved_at,
            ],

            // ── Purchase Order ──────────────────────────────────────
            'purchase_order' => [
                'kode'       => $po->kode,
                'order_date' => $po->order_date,
            ],

            // ── Supplier ────────────────────────────────────────────
            'supplier' => [
                'name'    => $supplier?->name,
                'code'    => $supplier?->code,
                'address' => $supplier?->address,
                'phone'   => $supplier?->phone,
                'email'   => $supplier?->email,
            ],

            // ── List Barang ─────────────────────────────────────────
            'items'       => $items,
            'grand_total' => $grandTotal,
        ]);
    }
}