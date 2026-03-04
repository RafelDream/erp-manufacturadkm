<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalesOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class SalesOrderController extends Controller
{
    public function index()
    {
        $orders = SalesOrder::with(['customer', 'items.product'])->latest()->get();
        return response()->json(['success' => true, 'data' => $orders]);
    }

    public function store(Request $request)
{
    $request->validate([
        'tanggal' => 'required|date',
        'customer_id' => 'required|exists:customers,id',
        'items' => 'required|array',
        'items.*.product_id' => 'required|exists:products,id',
        'items.*.qty' => 'required|numeric|min:1',
        'items.*.price' => 'required|numeric|min:0',
    ]);

    return DB::transaction(function () use ($request) {

        $noSpk = 'SPK-' . date('Ymd') . '-' . strtoupper(Str::random(4));

        $total = 0;

        $order = SalesOrder::create([
            'no_spk' => $noSpk,
            'tanggal' => $request->tanggal,
            'customer_id' => $request->customer_id,
            'notes' => $request->notes,
            'status' => 'pending',
            'created_by' => Auth::id() ?? 1,
            'total_price' => 0,
        ]);

        foreach ($request->items as $item) {

            $subtotal = $item['qty'] * $item['price'];
            $total += $subtotal;

            $order->items()->create([
                'product_id' => $item['product_id'],
                'qty_pesanan' => $item['qty'],
                'qty_shipped' => 0,
                'price' => $item['price'],
                'subtotal' => $subtotal,
            ]);
        }

        // update total setelah loop
        $order->update([
            'total_price' => $total
        ]);

        return response()->json([
            'success' => true,
            'data' => $order->load(['customer', 'items.product'])
        ], 201);
    });
}


    public function show($id)
    {
        $order = SalesOrder::with(['customer', 'items.product'])->find($id);
        if (!$order) return response()->json(['message' => 'Data tidak ditemukan'], 404);
        return response()->json(['success' => true, 'data' => $order]);
    }

    /**
     * Update data SPK (Hanya Header)
     */
    public function update(Request $request, $id)
    {
        $order = SalesOrder::find($id);

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        if ($order->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'SPK sudah selesai dan tidak bisa diubah'
            ], 400);
        }


        $request->validate([
            'tanggal' => 'sometimes|date',
            'customer_id' => 'sometimes|exists:customers,id',
            'status' => 'sometimes|in:pending,approved,partial,completed,cancelled',

        ]);

        $order->update($request->only(['tanggal', 'customer_id', 'notes', 'status']));

        return response()->json([
            'success' => true,
            'message' => 'Surat Pesanan Konsumen berhasil diperbarui',
            'data' => $order
        ]);
    }

    /**
     * Hapus SPK (Soft Delete)
     */
    public function destroy($id)
    {
        $order = SalesOrder::find($id);

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        // Soft delete akan otomatis mengisi kolom deleted_at
        $order->delete();

        return response()->json([
            'success' => true,
            'message' => 'Surat Pesanan Konsumen berhasil dihapus sementara'
        ]);
    }

    /**
     * Mengembalikan SPK dari sampah
     */
    public function restore($id)
    {
        $order = SalesOrder::onlyTrashed()->find($id);

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan di sampah'], 404);
        }

        $order->restore();

        return response()->json([
            'success' => true,
            'message' => 'Surat Pesanan Konsumen berhasil dikembalikan',
            'data' => $order
        ]);
    }
    
        public function getOutstandingItems($id)
        {
        $order = SalesOrder::with(['items.product'])->find($id);

            if (!$order) {
            return response()->json(['message' => 'SPK tidak ditemukan'], 404);
        }

    $items = $order->items->map(function ($item) {
        return [
            'sales_order_item_id' => $item->id,
            'product_id'   => $item->product_id,
            'product_name' => $item->product->name,
            'qty_pesanan'  => (float) $item->qty_pesanan,
            'qty_terkirim' => (float) $item->qty_shipped,
            'qty_sisa'     => (float) $item->qty_remaining,
        ];

        })->filter(fn($item) => $item['qty_sisa'] > 0)->values();

        return response()->json([
        'success' => true,
        'data' => [
            'sales_order_id' => $order->id,
            'no_spk'         => $order->no_spk,
            'items'          => $items
        ]
        ]);
    }

    public function printPdf($id)
    {
        // Pastikan relasi customer dan items.product ada di Model SalesOrder
        $order = SalesOrder::with(['customer', 'items.product'])->findOrFail($id);

        // Sesuaikan folder dan nama file: salesorders.sales_order_pdf
        $pdf = Pdf::loadView('salesorders.sales_order', compact('order'));
    
        return $pdf->stream('Sales-Order-'.$order->so_number.'.pdf');
    }
}
