<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountPayable;
use App\Models\PayablePayment;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PayablePaymentController extends Controller
{
    /**
     * GET - List Pembayaran
     */
    public function index(Request $request)
    {
        $query = PayablePayment::with([
            'accountPayable.invoiceReceipt',
            'supplier',
            'paymentAccount',
            'creator',
            'confirmedBy',
        ]);

        if ($request->supplier_id) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->payment_method) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->start_date && $request->end_date) {
            $query->whereBetween('payment_date', [$request->start_date, $request->end_date]);
        }

        return response()->json($query->latest()->get());
    }

    /**
     * GET - Detail Pembayaran
     */
    public function show($id)
    {
        return response()->json(
            PayablePayment::with([
                'accountPayable.invoiceReceipt.purchaseOrder.supplier',
                'accountPayable.invoice',
                'supplier',
                'paymentAccount',
                'journalEntry.lines.account',
                'creator',
                'confirmedBy',
            ])->findOrFail($id)
        );
    }

    /**
     * POST - Buat Draft Pembayaran
     * Pembayaran harus LUNAS sekaligus (amount = remaining_amount).
     * 
     * Contoh request:
     * {
     *   "account_payable_id": 1,
     *   "payment_method": "bank_transfer",   // cash | bank_transfer | credit_card | giro_cek
     *   "payment_account_id": 5,             // ID akun COA kas/bank
     *   "payment_date": "2026-03-04",
     *   "reference_number": "TRF-001",       // (opsional)
     *   "bank_name": "Mandiri",                  //  (opsional)
     *   "account_number": "1234567890",      // (opsional)
     *   "notes": "Pembayaran lunas invoice 12367"
     * }
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'account_payable_id' => 'required|exists:account_payables,id',
            'payment_method'     => 'required|in:cash,bank_transfer,credit_card,giro_cek',
            'payment_account_id' => 'required|exists:chart_of_accounts,id',
            'payment_date'       => 'required|date',
            'reference_number'   => 'nullable|string|max:100',
            'bank_name'          => 'nullable|string|max:100',
            'account_number'     => 'nullable|string|max:50',
            'notes'              => 'nullable|string',
        ]);

        $payable = AccountPayable::findOrFail($validated['account_payable_id']);

        if ($payable->status !== 'unpaid') {
            return response()->json([
                'message' => 'Hutang ini sudah lunas atau tidak valid'
            ], 422);
        }

        // Cek tidak ada payment draft lain untuk hutang ini
        if (PayablePayment::where('account_payable_id', $payable->id)
            ->where('status', 'draft')
            ->exists()) {
            return response()->json([
                'message' => 'Sudah ada draft pembayaran untuk hutang ini. Konfirmasi atau batalkan terlebih dahulu.'
            ], 422);
        }

        // Validasi akun COA harus bertipe asset (kas/bank)
        $paymentAccount = ChartOfAccount::findOrFail($validated['payment_account_id']);
        if ($paymentAccount->type !== 'asset') {
            return response()->json([
                'message' => 'Akun pembayaran harus bertipe asset (kas/bank)'
            ], 422);
        }

        // Jumlah pembayaran = hutang penuh (lunas sekaligus)
        $amount = $payable->remaining_amount;

        $payment = PayablePayment::create([
            'payment_number'     => 'PAY-' . now()->format('YmdHis'),
            'account_payable_id' => $payable->id,
            'supplier_id'        => $payable->supplier_id,
            'payment_method'     => $validated['payment_method'],
            'payment_account_id' => $validated['payment_account_id'],
            'payment_date'       => $validated['payment_date'],
            'amount'             => $amount,
            'reference_number'   => $validated['reference_number'] ?? null,
            'bank_name'          => $validated['bank_name'] ?? null,
            'account_number'     => $validated['account_number'] ?? null,
            'notes'              => $validated['notes'] ?? null,
            'status'             => 'draft',
            'created_by'         => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Draft pembayaran berhasil dibuat',
            'data'    => $payment->load('accountPayable', 'supplier', 'paymentAccount'),
        ], 201);
    }

    /**
     * PUT - Update Draft Pembayaran (hanya status draft)
     */
    public function update(Request $request, $id)
    {
        $payment = PayablePayment::findOrFail($id);

        if ($payment->status !== 'draft') {
            return response()->json([
                'message' => 'Hanya pembayaran draft yang bisa diupdate'
            ], 422);
        }

        $validated = $request->validate([
            'payment_method'   => 'required|in:cash,bank_transfer,credit_card,giro_cek',
            'payment_account_id' => 'required|exists:chart_of_accounts,id',
            'payment_date'     => 'required|date',
            'reference_number' => 'nullable|string|max:100',
            'bank_name'        => 'nullable|string|max:100',
            'account_number'   => 'nullable|string|max:50',
            'notes'            => 'nullable|string',
        ]);

        // Validasi akun COA
        $paymentAccount = ChartOfAccount::findOrFail($validated['payment_account_id']);
        if ($paymentAccount->type !== 'asset') {
            return response()->json([
                'message' => 'Akun pembayaran harus bertipe asset (kas/bank)'
            ], 422);
        }

        $payment->update($validated);

        return response()->json([
            'message' => 'Draft pembayaran berhasil diupdate',
            'data'    => $payment->load('accountPayable', 'supplier', 'paymentAccount'),
        ]);
    }

    /**
     * POST - Konfirmasi Pembayaran (hutang lunas)
     * Jurnal yang terbentuk saat konfirmasi:
     *   DEBIT  : Utang Usaha
     *   CREDIT : Kas / Bank
     */
    public function confirm($id)
    {
        $payment = PayablePayment::with('accountPayable')->findOrFail($id);

        if ($payment->status !== 'draft') {
            return response()->json([
                'message' => 'Hanya pembayaran draft yang bisa dikonfirmasi'
            ], 422);
        }

        $payable = $payment->accountPayable;

        if ($payable->status !== 'unpaid') {
            return response()->json([
                'message' => 'Hutang sudah tidak berstatus unpaid'
            ], 422);
        }

        // Fallback: cari berdasarkan category utang_lancar jika code berubah
        $payableAccount = ChartOfAccount::where('code', '2.1.01')
            ->where('type', 'liability')
            ->first()
            ?? ChartOfAccount::where('type', 'liability')
                ->where('category', 'utang_lancar')
                ->first();

        if (!$payableAccount) {
            return response()->json([
                'message' => 'Akun COA Utang Usaha (code: 2.1.01 / category: utang_lancar) tidak ditemukan.'
            ], 422);
        }

        try {
            DB::transaction(function () use ($payment, $payable, $payableAccount) {
                // 1. Buat Jurnal Pembayaran
                // DEBIT  Utang Usaha
                // CREDIT Kas/Bank
                $journal = JournalEntry::create([
                    'journal_number' => 'JRN-' . now()->format('YmdHis'),
                    'journal_date'   => $payment->payment_date,
                    'description'    => 'Pembayaran hutang #' . $payable->payable_number .
                                        ' via ' . $payment->payment_method_label,
                    'reference_type' => PayablePayment::class,
                    'reference_id'   => $payment->id,
                    'status'         => 'posted',
                    'created_by'     => Auth::id(),
                ]);

                JournalEntryLine::create([
                    'journal_entry_id' => $journal->id,
                    'account_id'       => $payableAccount->id,
                    'debit'            => $payment->amount,
                    'credit'           => 0,
                    'description'      => 'Pelunasan hutang ' . $payable->payable_number,
                ]);

                JournalEntryLine::create([
                    'journal_entry_id' => $journal->id,
                    'account_id'       => $payment->payment_account_id,
                    'debit'            => 0,
                    'credit'           => $payment->amount,
                    'description'      => 'Pembayaran via ' . $payment->payment_method_label,
                ]);

                // 2. Update payment
                $payment->update([
                    'status'         => 'confirmed',
                    'journal_entry_id' => $journal->id,
                    'confirmed_by'   => Auth::id(),
                    'confirmed_at'   => now(),
                ]);

                // 3. Update hutang jadi lunas
                $payable->update([
                    'paid_amount'      => $payable->amount,
                    'remaining_amount' => 0,
                    'status'           => 'paid',
                ]);
            });

            return response()->json([
                'message' => 'Pembayaran berhasil dikonfirmasi. Hutang lunas.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal konfirmasi pembayaran',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST - Batalkan Pembayaran (hanya draft)
     */
    public function cancel($id)
    {
        $payment = PayablePayment::findOrFail($id);

        if ($payment->status !== 'draft') {
            return response()->json([
                'message' => 'Hanya pembayaran draft yang bisa dibatalkan'
            ], 422);
        }

        $payment->update([
            'status'       => 'cancelled',
            'cancelled_by' => Auth::id(),
            'cancelled_at' => now(),
        ]);

        return response()->json([
            'message' => 'Pembayaran berhasil dibatalkan'
        ]);
    }

    /**
     * DELETE - Soft delete (hanya draft)
     */
    public function destroy($id)
    {
        $payment = PayablePayment::findOrFail($id);

        if ($payment->status !== 'draft') {
            return response()->json([
                'message' => 'Hanya pembayaran draft yang bisa dihapus'
            ], 422);
        }

        $payment->delete();

        return response()->json([
            'message' => 'Pembayaran berhasil dihapus'
        ]);
    }

    /**
     * GET - Data untuk cetak bukti pembayaran
     */
    public function print($id)
    {
        $payment = PayablePayment::with([
            'accountPayable.invoiceReceipt.purchaseOrder',
            'accountPayable.invoice',
            'supplier',
            'paymentAccount',
            'confirmedBy',
            'creator',
        ])->findOrFail($id);

        $payable = $payment->accountPayable;
        $receipt = $payable->invoiceReceipt;
        $invoice = $payable->invoice;
        $supplier = $payment->supplier;

        return response()->json([
            'payment' => [
                'payment_number'   => $payment->payment_number,
                'payment_date'     => $payment->payment_date,
                'payment_method'   => $payment->payment_method_label,
                'amount'           => (float) $payment->amount,
                'reference_number' => $payment->reference_number,
                'bank_name'        => $payment->bank_name,
                'account_number'   => $payment->account_number,
                'payment_account'  => $payment->paymentAccount?->name,
                'status'           => $payment->status,
                'confirmed_by'     => $payment->confirmedBy?->name,
                'confirmed_at'     => $payment->confirmed_at,
                'notes'            => $payment->notes,
            ],
            'payable' => [
                'payable_number' => $payable->payable_number,
                'invoice_date'   => $payable->invoice_date,
                'due_date'       => $payable->due_date,
                'amount'         => (float) $payable->amount,
            ],
            'invoice' => [
                'invoice_number' => $invoice?->invoice_number,
                'invoice_date'   => $invoice?->invoice_date,
                'due_date'       => $invoice?->due_date,
                'amount'         => (float) ($invoice?->amount ?? 0),
            ],
            'receipt' => [
                'receipt_number'   => $receipt?->receipt_number,
                'transaction_date' => $receipt?->transaction_date,
                'po_kode'          => $receipt?->purchaseOrder?->kode,
            ],
            'supplier' => [
                'name'    => $supplier?->nama,
                'address' => $supplier?->alamat,
                'phone'   => $supplier?->telepon,
                'email'   => $supplier?->email,
            ],
            'created_by' => $payment->creator?->name,
        ]);
    }
}