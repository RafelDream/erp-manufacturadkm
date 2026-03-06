<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountPayable;
use App\Models\InvoiceReceipt;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AccountPayableController extends Controller
{
    /**
     * GET - List Utang usaha
     * Mendukung filter untuk aging payable report.
     */
    public function index(Request $request)
    {
        $query = AccountPayable::with([
            'supplier',
            'invoiceReceipt',
            'invoice',
            'creator',
        ]);

        if ($request->supplier_id) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->overdue === 'true') {
            $query->where('status', 'unpaid')
                  ->where('due_date', '<', now());
        }

        if ($request->due_from && $request->due_to) {
            $query->whereBetween('due_date', [$request->due_from, $request->due_to]);
        }

        $payables = $query->orderBy('due_date')->get()->map(function ($p) {
            $dueDate         = \Carbon\Carbon::parse($p->due_date);
            $isOverdue       = $p->status === 'unpaid' && $dueDate->lt(now());
            $p->is_overdue   = $isOverdue;
            $p->overdue_days = $isOverdue ? (int) $dueDate->diffInDays(now()) : 0;
            return $p;
        });

        return response()->json($payables);
    }

    /**
     * GET - Detail Hutang
     */
    public function show($id)
    {
        $payable = AccountPayable::with([
            'supplier',
            'invoiceReceipt.purchaseOrder',
            'invoice',
            'payments.paymentAccount',
            'payments.confirmedBy',
            'creator',
        ])->findOrFail($id);

        $dueDate               = \Carbon\Carbon::parse($payable->due_date);
        $isOverdue             = $payable->status === 'unpaid' && $dueDate->lt(now());
        $payable->is_overdue   = $isOverdue;
        $payable->overdue_days = $isOverdue ? (int) $dueDate->diffInDays(now()) : 0;

        return response()->json($payable);
    }

    /**
     * POST - Buat hutang dari TTF yang sudah approved.
     * Dipanggil otomatis setelah TTF di-approve ATAU manual.
     * Jurnal yang terbentuk:
     * DEBIT  : Persediaan / Beban (akun dari COA)
     * CREDIT : Utang Usaha
     */
    public function createFromInvoiceReceipt(Request $request)
    {
        $validated = $request->validate([
            'invoice_receipt_id'   => 'required|exists:invoice_receipts,id',
            'payable_account_id'   => 'required|exists:chart_of_accounts,id', // Akun Utang Usaha
            'inventory_account_id' => 'required|exists:chart_of_accounts,id', // Akun Persediaan / Beban
            'notes'                => 'nullable|string',
        ]);

        $receipt = InvoiceReceipt::with('invoices', 'purchaseOrder.supplier')
            ->findOrFail($validated['invoice_receipt_id']);

        if ($receipt->status !== 'approved') {
            return response()->json([
                'message' => 'Hutang hanya bisa dibuat dari TTF yang sudah approved'
            ], 422);
        }

        // Cek belum ada AP dari TTF ini
        if (AccountPayable::where('invoice_receipt_id', $receipt->id)->exists()) {
            return response()->json([
                'message' => 'Hutang untuk TTF ini sudah pernah dibuat'
            ], 422);
        }

        $invoice = $receipt->invoices->first();
        if (!$invoice) {
            return response()->json([
                'message' => 'TTF tidak memiliki invoice'
            ], 422);
        }

        try {
            $payable = DB::transaction(function () use ($validated, $receipt, $invoice) {
                // 1. Buat Account Payable
                $payable = AccountPayable::create([
                    'payable_number'      => 'AP-' . now()->format('YmdHis'),
                    'invoice_receipt_id'  => $receipt->id,
                    'invoice_id'          => $invoice->id,
                    'supplier_id'         => $receipt->supplier_id,
                    'amount'              => $invoice->amount,
                    'paid_amount'         => 0,
                    'remaining_amount'    => $invoice->amount,
                    'invoice_date'        => $invoice->invoice_date,
                    'due_date'            => $invoice->due_date,
                    'status'              => 'unpaid',
                    'notes'               => $validated['notes'] ?? null,
                    'created_by'          => Auth::id(),
                ]);

                // Jurnal otomatis
                // DEBIT  Persediaan/Beban
                // CREDIT Utang usaha
                $journal = JournalEntry::create([
                    'journal_number'  => 'JRN-' . now()->format('YmdHis'),
                    'journal_date'    => now()->toDateString(),
                    'description'     => 'Utang usaha dari TTF #' . $receipt->receipt_number,
                    'reference_type'  => AccountPayable::class,
                    'reference_id'    => $payable->id,
                    'status'          => 'posted',
                    'created_by'      => Auth::id(),
                ]);

                JournalEntryLine::create([
                    'journal_entry_id' => $journal->id,
                    'account_id'       => $validated['inventory_account_id'],
                    'debit'            => $invoice->amount,
                    'credit'           => 0,
                    'description'      => 'Pembelian dari ' . $receipt->purchaseOrder->kode,
                ]);

                JournalEntryLine::create([
                    'journal_entry_id' => $journal->id,
                    'account_id'       => $validated['payable_account_id'],
                    'debit'            => 0,
                    'credit'           => $invoice->amount,
                    'description'      => 'Utang usaha kepada supplier - ' . $receipt->receipt_number,
                ]);

                return $payable;
            });

            return response()->json([
                'message' => 'Hutang usaha berhasil dicatat',
                'data'    => $payable->load('supplier', 'invoice', 'invoiceReceipt'),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal membuat hutang usaha',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET - Aging Payable Report
     * Mengelompokkan hutang berdasarkan waktu (0-30, 31-60, 61-90, >90 hari)
     */
    public function agingReport(Request $request)
    {
        $payables = AccountPayable::with('supplier')
            ->where('status', 'unpaid')
            ->get();

        $aging = [
            'current'   => [],  // Belum jatuh tempo
            '1_30'      => [],  // 1-30 hari overdue
            '31_60'     => [],  // 31-60 hari overdue
            '61_90'     => [],  // 61-90 hari overdue
            'above_90'  => [],  // > 90 hari overdue
        ];

        foreach ($payables as $p) {
            $dueDate = \Carbon\Carbon::parse($p->due_date);
            $days    = $dueDate->isPast() ? -(int) $dueDate->diffInDays(now()) : (int) now()->diffInDays($dueDate);

            if ($days >= 0) {
                $aging['current'][] = $p;
            } elseif ($days >= -30) {
                $aging['1_30'][] = $p;
            } elseif ($days >= -60) {
                $aging['31_60'][] = $p;
            } elseif ($days >= -90) {
                $aging['61_90'][] = $p;
            } else {
                $aging['above_90'][] = $p;
            }
        }

        $summary = [];
        foreach ($aging as $bucket => $items) {
            $collection = collect($items);
            $summary[$bucket] = [
                'count'  => $collection->count(),
                'total'  => $collection->sum('remaining_amount'),
                'items'  => $items,
            ];
        }

        return response()->json([
            'as_of'        => now()->toDateString(),
            'grand_total'  => $payables->sum('remaining_amount'),
            'aging'        => $summary,
        ]);
    }

    /**
     * GET - Summary hutang per supplier
     */
    public function summaryBySupplier()
    {
        $summary = AccountPayable::with('supplier')
            ->where('status', 'unpaid')
            ->get()
            ->groupBy('supplier_id')
            ->map(function ($items) {
                $supplier = $items->first()->supplier;
                return [
                    'supplier_id'      => $items->first()->supplier_id,
                    'supplier_name'    => $supplier?->nama,
                    'total_payable'    => $items->sum('amount'),
                    'total_remaining'  => $items->sum('remaining_amount'),
                    'count'            => $items->count(),
                    'oldest_due_date'  => $items->min('due_date'),
                ];
            })
            ->values();

        return response()->json($summary);
    }
}