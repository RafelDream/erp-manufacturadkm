<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryOrder;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class SalesInvoiceController extends Controller
{
    public function index()
    {
        $invoices = SalesInvoice::with(['customer', 'salesOrder'])->latest()->paginate(10);
        return response()->json(['success' => true, 'data' => $invoices]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'sales_order_id' => 'required|exists:sales_orders,id',
            'payment_type'   => 'required|in:full,dp',
            'dp_amount'      => 'required_if:payment_type,dp|numeric|min:0',
        ]);

        return DB::transaction(function () use ($request) {
            $so = SalesOrder::with('items')->findOrFail($request->sales_order_id);
            $totalOriginal = $so->total_price;
            $gallonLoanQty = $this->calculateGallonLoan($request->sales_order_id);
            $discountPercentage = 0;
            if ($gallonLoanQty > 5) {
                $discountPercentage += 5; // Diskon 5%
            }

            $orderCount = SalesInvoice::where('customer_id', $so->customer_id)->count();
                if ($orderCount >= 2) { // Jika sudah ada 2 invoice sebelumnya, maka ini yang ke-3
                    $discountPercentage += 2; // Tambahan diskon 2%
                }

            $discountAmount = ($discountPercentage / 100) * $totalOriginal;
            $finalAmount = $totalOriginal - $discountAmount;

            $so = SalesOrder::findOrFail($request->sales_order_id);
            $lastDo = DeliveryOrder::whereHas('assignment.workOrder', function($q) use ($so) {
                    $q->where('sales_order_id', $so->id);
            })
            ->where('status', 'shipped')
            ->latest()
            ->first();

            $gallonLoanQty = 0;
            if (!$lastDo) {
                $gallonLoanQty = DB::table('delivery_order_items')
                ->join('products', 'delivery_order_items.product_id', '=', 'products.id')
                ->where('delivery_order_items.delivery_order_id', $lastDo->id)
                ->where('products.is_returnable', 1)
                ->sum('delivery_order_items.qty_realisasi');
                return response()->json([
                'success' => false, 
                'message' => 'Invoice tidak bisa dibuat karena belum ada Delivery Order yang dikirim.'
                ], 422);
            }

            $gallonLoanQty = $lastDo->items()
                ->whereHas('product', function($q) {
                    $q->where('is_returnable', 1);
            })->sum('qty_realisasi');

            if (SalesInvoice::where('sales_order_id', $so->id)->exists()) {
                return response()->json(['success' => false, 'message' => 'Invoice untuk SPK ini sudah pernah dibuat.'], 422);
            }

            $totalPrice = $so->total_price;
            $dpAmount   = $request->payment_type === 'dp' ? ($request->dp_amount ?? 0) : $totalPrice;
            $balanceDue = $totalPrice - $dpAmount;

            $discountAmount = ($discountPercentage / 100) * $totalOriginal;
            $priceAfterDiscount = $totalOriginal - $discountAmount;

            // --- LOGIKA PAJAK ---
            $ppnAmount = $priceAfterDiscount * 0.11; // PPN 11%
            $pphAmount = $priceAfterDiscount * 0.02;
            $finalAmount = ($priceAfterDiscount + $ppnAmount) - $pphAmount;

            $dpAmount = $request->payment_type === 'full' ? $finalAmount : ($request->dp_amount ?? 0);
            $balanceDue = max(0, $finalAmount - $dpAmount);

            $status  = ($request->payment_type === 'dp' && $balanceDue > 0) ? 'draft' : 'paid';
            $dueDate = $status === 'paid' ? now() : now()->addMonth()->day(25);

            $invoice = SalesInvoice::create([
                'no_invoice'     => 'INV-' . date('Ymd') . '-' . strtoupper(Str::random(4)),
                'sales_order_id' => $so->id,
                'delivery_order_id'     => $lastDo->id,
                'customer_id'    => $so->customer_id,
                'tanggal'        => now(),
                'due_date'       => $dueDate,
                'total_price'    => $totalPrice,
                'dp_amount'      => $dpAmount,
                'amount_paid'    => $dpAmount,
                'balance_due'    => $balanceDue,
                'gallon_loan_qty'       => $gallonLoanQty,
                'gallon_deposit_status' => $gallonLoanQty > 0 ? 'loaned' : 'none',
                'discount_amount' => $discountAmount,
                'ppn_amount'      => $ppnAmount,
                'pph_amount'      => $pphAmount,
                'total_amount' => $totalOriginal,     // Harga asli
                'final_amount' => $finalAmount,
                'payment_type'   => $request->payment_type,
                'status'         => $status,
                'notes'          => $request->payment_type === 'dp' ? 'Tagihan Down Payment (DP)' : 'Pelunasan Penuh',
                'created_by'     => Auth::id() ?? 1,
            ]);

            foreach ($so->items as $item) {
                $finalQty = $item->qty ?? $item->quantity ?? 1;
                $invoice->items()->create([
                    'product_id' => $item->product_id,
                    'qty'        => $finalQty ?? 0,
                    'price'      => $item->price ?? 0,
                    'subtotal'   => $item->subtotal ?? 0,
                ]);
            }

            return response()->json([
                'success'    => true, 
                'message'    => "Invoice berhasil dibuat. Diskon otomatis: " . $discountPercentage . "%", 
                'print_type' => $status === 'paid' ? 'INVOICE LUNAS' : 'INVOICE DP',
                'data'       => $invoice->fresh(['items', 'customer'])
            ], 201);
        });
    }

    public function show($id)
    {
        $invoice = SalesInvoice::with(['customer', 'salesOrder', 'items.product', 'installments'])->find($id);
        if (!$invoice) {
            return response()->json(['success' => false, 'message' => 'Invoice tidak ditemukan'], 404);
        }
        return response()->json(['success' => true, 'data' => $invoice]);
    }

    public function update(Request $request, $id)
    {
        $invoice = SalesInvoice::findOrFail($id);

        if ($invoice->status === 'paid') {
            return response()->json(['success' => false, 'message' => 'Gagal! Invoice lunas tidak dapat diubah.'], 422);
        }

        $invoice->update($request->only(['due_date', 'notes']));

        return response()->json(['success' => true, 'message' => 'Invoice diperbarui', 'data' => $invoice]);
    }

    public function destroy($id)
    {
        $invoice = SalesInvoice::findOrFail($id);

        if ($invoice->status === 'paid') {
            return response()->json(['success' => false, 'message' => 'Gagal! Invoice lunas tidak boleh dihapus.'], 422);
        }

        $invoice->delete();
        return response()->json(['success' => true, 'message' => 'Invoice dipindahkan ke sampah.']);
    }

    public function trash()
    {
        return response()->json(['success' => true, 'data' => SalesInvoice::onlyTrashed()->with('customer')->get()]);
    }

    public function restore($id)
    {
        $invoice = SalesInvoice::onlyTrashed()->findOrFail($id);
        $invoice->restore();
        return response()->json(['success' => true, 'message' => 'Invoice dipulihkan']);
    }

    public function forceDelete($id)
    {
        // Mendukung hapus dari sampah atau pembatalan transaksi langsung
        $invoice = SalesInvoice::withTrashed()->findOrFail($id);

        if ($invoice->status === 'paid') {
            return response()->json(['success' => false, 'message' => 'Invoice lunas tidak bisa dihapus permanen!'], 422);
        }

        $invoice->forceDelete();
        return response()->json(['success' => true, 'message' => 'Transaksi dibatalkan dan data dihapus permanen.']);
    }

    public function getPendingPayments()
    {
        $pending = SalesInvoice::whereIn('status', ['draft', 'partial'])
                    ->where('balance_due', '>', 0)
                    ->with('customer')
                    ->get();

        return response()->json(['success' => true, 'data' => $pending]);
    }

    public function payRemainder($id)
    {
        $invoice = SalesInvoice::findOrFail($id);

        if ($invoice->balance_due <= 0) {
            return response()->json(['message' => 'Invoice sudah lunas.'], 422);
        }

        return DB::transaction(function () use ($invoice) {
            $invoice->update([
                'amount_paid' => $invoice->total_price,
                'balance_due' => 0,
                'status'      => 'paid',
                'notes'       => 'Pelunasan lunas (Full Payment)',
            ]);

            return response()->json(['success' => true, 'message' => 'Pembayaran lunas berhasil.', 'data' => $invoice]);
        });
    }

    public function payInstallment(Request $request, $id)
    {
        $invoice = SalesInvoice::with('installments')->findOrFail($id);

        if ($invoice->installments->count() >= 6) {
            return response()->json(['message' => 'Batas maksimal cicilan (6x) tercapai.'], 422);
        }

        if ($invoice->status === 'paid') {
            return response()->json(['message' => 'Invoice ini sudah lunas.'], 422);
        }

        $request->validate(['amount' => 'required|numeric|min:1000']);

        return DB::transaction(function () use ($request, $invoice) {
            $today     = now()->startOfDay();
            $dueDate   = Carbon::parse($invoice->due_date)->startOfDay();
            $fineTotal = $today->gt($dueDate) ? ($invoice->balance_due * 0.05) : 0;

            if ($today->greaterThan($dueDate)) {
                $fineRate = 0.05;
                $fineTotal = $invoice->balance_due * $fineRate;
                } else {
                $fineTotal = 0; // Pastikan defaultnya 0 jika tidak telat
            }   
            
            $instNumber = $invoice->installments->count() + 1;
            $receiptNo  = 'RCP-' . $invoice->no_invoice . '-' . $instNumber;

            // Simpan Data Cicilan
            $invoice->installments()->create([
                'installment_number' => $instNumber,
                'amount'             => $request->amount,
                'fine_paid'          => $fineTotal,
                'payment_date'       => $today,
                'receipt_no'         => $receiptNo,
                'notes'              => $request->notes ?? "Cicilan ke-$instNumber",
            ]);

            $netPayment = max(0, $request->amount - $fineTotal);
            $newPaid    = $invoice->amount_paid + $netPayment;
            $newBalance = max(0, $invoice->total_price - $newPaid);
            $isPaid     = $newBalance <= 0;

            $invoice->update([
                'amount_paid' => $newPaid,
                'balance_due' => $newBalance,
                'total_fines' => $invoice->total_fines + $fineTotal,
                'status'      => $isPaid ? 'paid' : 'draft',
                'due_date'    => $isPaid ? $invoice->due_date : now()->addMonth()->day(25),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cicilan berhasil dicatat.',
                'data'    => [
                    'receipt_no'        => $receiptNo,
                    'fine_applied'      => round($fineTotal, 2),
                    'is_late'           => $today->gt($dueDate),
                    'remaining_balance' => round($newBalance, 2),
                    'status'            => strtoupper($invoice->status),
                    'next_due_date'     => $isPaid ? 'LUNAS' : $invoice->due_date
                ]
            ]);
        });
    }

    public function returnGallon($id)
    {
    // 1. Cari data invoice-nya
    $invoice = SalesInvoice::findOrFail($id);
    
    // 2. Cek apakah invoice ini memang punya pinjaman galon
    if ($invoice->gallon_loan_qty <= 0) {
        return response()->json([
            'success' => false,
            'message' => 'Invoice ini tidak memiliki catatan pinjaman galon.'
        ], 422);
    }

    // 3. Update statusnya menjadi returned
    $invoice->update([
        'gallon_deposit_status' => 'returned'
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Galon kosong telah diterima. Status deposit diperbarui menjadi RETURNED.',
        'data' => [
            'no_invoice' => $invoice->no_invoice,
            'gallon_loan_qty' => $invoice->gallon_loan_qty,
            'gallon_deposit_status' => $invoice->gallon_deposit_status
        ]
    ]);
    }

    private function calculateGallonLoan($salesOrderId)
    {
    // Cari DO yang statusnya shipped untuk SO ini
    $lastDo = DeliveryOrder::whereHas('assignment.workOrder', function($q) use ($salesOrderId) {
        $q->where('sales_order_id', $salesOrderId);
    })->where('status', 'shipped')->latest()->first();

    $qty = 0;
    if ($lastDo) {
        // Gunakan Query Builder agar lebih aman dan cepat
        $qty = DB::table('delivery_order_items')
            ->join('products', 'delivery_order_items.product_id', '=', 'products.id')
            ->where('delivery_order_items.delivery_order_id', $lastDo->id)
            ->where('products.is_returnable', 1)
            ->sum('delivery_order_items.qty_realisasi');
    }

    return $qty;
    }

    public function getLedgerReport(Request $request)
    {
    $request->validate([
        'customer_id' => 'required|exists:customers,id',
        'start_date'  => 'nullable|date',
        'end_date'    => 'nullable|date',
    ]);

    $customerId = $request->customer_id;
    $startDate  = $request->start_date ?? now()->startOfYear();
    $endDate    = $request->end_date ?? now()->endOfDay();

    // 1. Ambil semua invoice dalam periode tersebut
    $invoices = SalesInvoice::where('customer_id', $customerId)
        ->whereBetween('tanggal', [$startDate, $endDate])
        ->with(['installments'])
        ->get();

    // 2. Kalkulasi Ringkasan Finansial (Summary)
    $summary = [
        'total_invoiced'     => $invoices->sum('final_amount'),
        'total_paid_to_date' => $invoices->sum('amount_paid'), // Termasuk DP
        'total_outstanding'  => $invoices->sum('balance_due'),
        'total_fines_earned' => $invoices->sum('total_fines'),
        'gallon_summary' => [
            'total_loaned'   => $invoices->sum('gallon_loan_qty'),
            'total_returned' => $invoices->where('gallon_deposit_status', 'returned')->sum('gallon_loan_qty'),
            'still_at_customer' => $invoices->where('gallon_deposit_status', '!=', 'returned')->sum('gallon_loan_qty'),
        ]
    ];

    // 3. Susun Riwayat Transaksi Kronologis (Ledger Entries)
    $ledger = collect();

    foreach ($invoices as $inv) {
        // Entry Penjualan (Debit)
        $ledger->push([
            'date'        => $inv->tanggal->format('Y-m-d'),
            'description' => "Penjualan: Invoice {$inv->no_invoice}",
            'reference'   => $inv->no_invoice,
            'type'        => 'DEBIT',
            'amount'      => $inv->final_amount,
        ]);

        // Entry DP jika ada (Kredit)
        if ($inv->dp_amount > 0) {
            $ledger->push([
                'date'        => $inv->tanggal->format('Y-m-d'),
                'description' => "Pembayaran DP: {$inv->no_invoice}",
                'reference'   => $inv->no_invoice,
                'type'        => 'CREDIT',
                'amount'      => $inv->dp_amount,
            ]);
        }

        // Entry Cicilan dari tabel installments (Kredit)
        foreach ($inv->installments as $ins) {
            $ledger->push([
                'date'        => $ins->payment_date->format('Y-m-d'),
                'description' => "Cicilan #{$ins->installment_number}: {$inv->no_invoice}",
                'reference'   => $ins->receipt_no,
                'type'        => 'CREDIT',
                'amount'      => $ins->amount - $ins->fine_paid, // Hanya pokok yang mengurangi piutang
            ]);
        }
    }

    // Urutkan berdasarkan tanggal
    $sortedLedger = $ledger->sortBy('date')->values();

    return response()->json([
        'success' => true,
        'customer_info' => Customer::find($customerId),
        'period' => [
            'from' => $startDate,
            'to' => $endDate
        ],
        'summary' => $summary,
        'ledger_history' => $sortedLedger
    ]);
    }

    public function downloadPDF($id)
    {
    // Ambil data lengkap dengan relasinya
    $invoice = SalesInvoice::with(['customer', 'items.product'])->findOrFail($id);

    // Load view dan passing datanya
    $pdf = Pdf::loadView('invoices.print', compact('invoice'));

    // Set kertas A4 (atau sesuai kebutuhan printer thermal/kantor)
    $pdf->setPaper('a4', 'portrait');

    // Download file dengan nama invoice
    return $pdf->stream('Invoice-' . $invoice->no_invoice . '.pdf');
    }
}