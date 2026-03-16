<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalesReturn;
use App\Models\Warehouse;
use App\Models\SalesInvoice;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class SalesReturnController extends Controller
{
    // 1. READ (Tampilkan semua retur)
    public function index()
    {
        $returns = SalesReturn::with(['items.product', 'invoice.customer', 'invoice'])->latest()->get();
        return response()->json(['success' => true, 'data' => $returns]);
    }

    // 2. CREATE (Simpan Retur & Update Stok/Piutang)
    public function store(Request $request)
    {
        $request->validate([
            'sales_invoice_id' => 'required|exists:sales_invoices,id',
            'return_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.condition' => 'required|in:good,damaged,reject',
            'items.*.price' => 'required|numeric',
        ]);

        return DB::transaction(function () use ($request) {
            $invoice = SalesInvoice::findOrFail($request->sales_invoice_id);
            $warehouseId = $invoice->warehouse_id ?? Warehouse::first()->id;;

            $returnNo = 'RJ-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
            
            $return = SalesReturn::create([
                'return_no' => $returnNo,
                'sales_invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'return_date' => $request->return_date,
                'total_return_amount' => 0,
                'reason' => $request->reason,
                'created_by' => Auth::id() ?? 1,
            ]);

            $totalReturn = 0;
            foreach ($request->items as $itemData) {
                $subtotal = $itemData['qty'] * $itemData['price'];
                $totalReturn += $subtotal;

                $return->items()->create([
                    'product_id' => $itemData['product_id'],
                    'qty' => $itemData['qty'],
                    'condition' => $itemData['condition'],
                    'price' => $itemData['price'],
                    'subtotal' => $subtotal
                ]);

                // Update Stok: Barang kembali ke gudang
                if ($itemData['condition'] === 'good') {
                    Stock::updateOrCreate(
                        ['product_id' => $itemData['product_id'], 'warehouse_id' => $warehouseId],
                        ['quantity' => DB::raw("quantity + " . $itemData['qty'])]
                    );

                    StockMovement::create([
                        'product_id'   => $itemData['product_id'],
                        'warehouse_id' => $warehouseId,
                        'type'         => 'in',
                        'quantity'     => $itemData['qty'],
                        'reference_id' => $returnNo,
                        'notes'        => 'Retur Penjualan (Good Condition) #' . $returnNo,
                        'created_by'   => Auth::id() ?? 1,
                    ]);
                }
            }

            $return->update(['total_return_amount' => $totalReturn]);
            
            // Kurangi Piutang di Invoice
            if ($invoice->balance_due > 0) {
                $newBalance = $invoice->balance_due - $totalReturn;
                $invoice->update([
                    'balance_due' => $newBalance < 0 ? 0 : $newBalance
                ]);
            }

            return response()->json([
                'success' => true, 
                'message' => 'Retur berhasil diproses. Stok bertambah di tabel stocks untuk kondisi "good".', 
                'data' => $return->load('items.product', 'invoice.customer')
            ], 201);
        });
    }

    // 3. SHOW (Detail Retur Tunggal)
    public function show($id)
    {
        $return = SalesReturn::with('items.product')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $return]);
    }

    // 4. DELETE (Soft Delete & Balikkan Stok/Piutang)
    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {
            $return = SalesReturn::with('items')->findOrFail($id);
            $invoice = SalesInvoice::find($return->sales_invoice_id);
            $warehouseId = $invoice->warehouse_id ?? Warehouse::first()->id;

            // Balikkan stok (karena retur dibatalkan/dihapus, stok ditarik lagi dari gudang)
            foreach ($return->items as $item) {
                if ($item->condition === 'good') {
                   Stock::where('product_id', $item->product_id)
                        ->where('warehouse_id', $warehouseId)
                        ->decrement('quantity', $item->qty);
                    
                    StockMovement::create([
                        'product_id'   => $item->product_id,
                        'warehouse_id' => $warehouseId,
                        'type'         => 'out',
                        'quantity'     => $item->qty,
                        'reference_id' => $return->return_no,
                        'notes'        => 'Pembatalan Retur (Hapus Data) #' . $return->return_no,
                        'created_by'   => Auth::id() ?? 1,
                    ]);
                }
            }

            // Kembalikan piutang ke invoice
            if ($invoice) {
                $invoice->increment('balance_due', $return->total_return_amount);
            }

            $return->delete(); // Ini akan melakukan Soft Delete
            return response()->json(['success' => true, 'message' => 'Data Retur berhasil dihapus']);
        });
    }

    // 5. RESTORE (Mengembalikan data yang dihapus)
    public function restore($id)
    {
        return DB::transaction(function () use ($id) {
            $return = SalesReturn::withTrashed()->with('items')->findOrFail($id);
            
            if ($return->trashed()) {
                $return->restore();
                $invoice = SalesInvoice::find($return->sales_invoice_id);
                $warehouseId = $invoice->warehouse_id ?? Warehouse::first()->id;

                // Kembalikan logika stok (Barang masuk lagi ke gudang)
                foreach ($return->items as $item) {
                    if ($item->condition === 'good') {
                        Stock::updateOrCreate(
                            ['product_id' => $item->product_id, 'warehouse_id' => $warehouseId],
                            ['quantity' => DB::raw("quantity + " . $item->qty)]
                        );
                    }       
                }
                
                // Potong piutang lagi
                if ($invoice) {
                    $newBalance = max(0, $invoice->balance_due - $return->total_return_amount);
                    $invoice->update(['balance_due' => $newBalance]);
                }    

                return response()->json(['success' => true, 'message' => 'Data retur berhasil dipulihkan']);
            }
            
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan di sampah'], 404);
        });
    }

    public function printPdf($id)
    {
        $return = SalesReturn::with(['items.product', 'invoice.customer'])->findOrFail($id);

        // Gunakan titik (.) untuk memisahkan folder dan nama file
        $pdf = Pdf::loadView('salesreturns.sales_return', compact('return'));
    
        return $pdf->stream('Nota-Retur-'.$return->return_no.'.pdf');
    }
}
