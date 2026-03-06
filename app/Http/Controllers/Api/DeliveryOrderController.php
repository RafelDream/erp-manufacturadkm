<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryOrder;
use App\Models\SalesOrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class DeliveryOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = DeliveryOrder::with(['customer', 'warehouse', 'salesOrder']);

        if ($request->filled(['tanggal_awal', 'tanggal_akhir'])) {
            $query->whereBetween('tanggal_sj', [$request->tanggal_awal, $request->tanggal_akhir]);
        }

        $data = $query->latest()->paginate($request->per_page ?? 10);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Menyimpan Surat Jalan Baru (Final Version)
     */
    public function store(Request $request)
    {
        $request->validate([
            'tanggal'    => 'required|date',
            'sales_order_id' => 'required|exists:sales_orders,id',
            'customer_id'   => 'required|exists:customers,id',
            'warehouse_id'  => 'required|exists:warehouses,id',
            'status'        => 'required|in:draft,shipped',
            'items'         => 'required|array|min:1',
            'items.*.sales_order_item_id' => 'required|exists:sales_order_items,id',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty'        => 'required|numeric|min:0.1',
        ]);

        return DB::transaction(function () use ($request) {
            // Generate No Dokumen Otomatis
            $noSj = 'SJ-' . date('Ymd') . '-' . strtoupper(Str::random(4));

            //Simpan Header (Data Dokumen)
            $do = DeliveryOrder::create([
                'no_sj'                  => $noSj,
                'sales_order_id'         => $request->sales_order_id,
                'tanggal'             => $request->tanggal,
                'no_spk'                 => $request->no_spk,
                'customer_id'            => $request->customer_id,
                'warehouse_id'           => $request->warehouse_id,
                'notes'                  => $request->notes,
                'status'                 => $request->status,
                'created_by'             => Auth::id() ?? 1,
            ]);
            
            foreach ($request->items as $item) {
            $do->items()->create([
                'sales_order_item_id' => $item['sales_order_item_id'],
                'product_id'    => $item['product_id'],
                'qty_realisasi' => $item['qty'],
            ]);

            if ($request->status === 'shipped') {
                $this->processShipping($do, $item);
            }
        }
            $do->salesOrder->syncStatus();

            return response()->json([
            'success' => true,
            'message' => $request->status === 'shipped' ? 'Surat Jalan Berhasil Dikirim & Stok Terpotong' : 'Surat Jalan Disimpan sebagai Draft',
            'data'    => $do->load('items.product', 'customer')
            ], 201);
        });
    }

    public function sendOrder($id)
    {
    $do = DeliveryOrder::with('items')->findOrFail($id);

    if ($do->status === 'shipped') {
        return response()->json(['message' => 'Surat jalan sudah berstatus terkirim.'], 422);
    }

    return DB::transaction(function () use ($do) {
        foreach ($do->items as $item) {
            $this->reduceStock($do, $item->product_id, $item->qty_realisasi);
        }

        $do->update(['status' => 'shipped']);

        return response()->json([
            'success' => true,
            'message' => 'Surat jalan berhasil dikirim, stok sekarang terpotong.',
            'data' => $do
            ]);
        });
    }

    // Fungsi Helper agar kode tidak berulang
    private function reduceStock($do, $productId, $qty)
    {
    StockMovement::create([
        'product_id'   => $productId,
        'warehouse_id' => $do->warehouse_id,
        'type'         => 'out',
        'quantity'     => $qty,
        'reference_id' => $do->no_sj,
        'notes'        => 'Pengiriman SJ: ' . $do->no_sj,
        'created_by'   => Auth::id() ?? 1,
        ]);
    }

    /**
     * Menampilkan Detail Surat Jalan
     */
    public function show($id)
    {
        $do = DeliveryOrder::with(['customer', 'warehouse', 'items.product', 'creator', 'salesOrder'])->find($id);

        if (!$do) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        return response()->json(['success' => true, 'data' => $do]);
    }

    public function update(Request $request, $id)
    {
        $do = DeliveryOrder::find($id);

        if (!$do) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $request->validate([
            'tanggal'     => 'sometimes|date',
            'expedition'     => 'sometimes|string',
            'vehicle_number' => 'sometimes|string',
        ]);

        $do->update($request->only([
            'tanggal', 'no_spk', 'expedition', 'vehicle_number', 'notes'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Data Surat Jalan berhasil diperbarui',
            'data' => $do
        ]);
    }

    /**
     * Hapus Surat Jalan (Soft Delete)
     */
    public function destroy($id)
    {
        $do = DeliveryOrder::with('items')->find($id);

        if (!$do) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        return DB::transaction(function () use ($do) {
            // Jika sudah shipped, kembalikan stok dan kurangi qty_shipped di SO
            if ($do->status === 'shipped') {
                foreach ($do->items as $item) {
                    $this->reverseShipping($do, $item);
                }
            }

            $do->delete();
            
            if ($do->salesOrder) {
                $do->salesOrder->syncStatus();
            }

            return response()->json([
                'success' => true,
                'message' => 'Surat Jalan Berhasil Dihapus & Stok Dikembalikan'
            ]);
        });
    }

    /**
     * Restore Surat Jalan yang dihapus
     */
    public function restore($id)
    {
        $do = DeliveryOrder::onlyTrashed()->with('items')->find($id);

        if (!$do) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan di sampah'], 404);
        }

        return DB::transaction(function () use ($do) {
            $do->restore();

            if ($do->status === 'shipped') {
                foreach ($do->items as $item) {
                    $this->processShipping($do, [
                    'sales_order_item_id' => $item->sales_order_item_id,
                    'product_id'          => $item->product_id,
                    'qty'                 => $item->qty_realisasi, // Mapping ke 'qty'
                ]);
                }
            }

            $do->salesOrder->syncStatus();

            return response()->json([
                'success' => true,
                'message' => 'Data Surat Jalan Berhasil Dikembalikan',
                'data' => $do
            ]);
        });
    }

        public function confirmReceived($id)
    {
        $do = DeliveryOrder::findOrFail($id);

        if ($do->status !== 'shipped') {
        return response()->json([
            'success' => false,
            'message' => 'Barang belum dikirim atau sudah berstatus diterima.'
        ], 422);
    }

    return DB::transaction(function () use ($do) {
        // 2. Update status menjadi received
        $do->update([
            'status' => 'received',
            // Anda bisa menambah kolom 'received_at' jika ada di migration
        ]);
        // 3. (Opsional) Jika ingin otomatis mengupdate status Sales Order jika perlu
        if ($do->salesOrder) {
            $do->salesOrder->syncStatus();
        }

        return response()->json([
            'success' => true,
            'message' => 'Konfirmasi berhasil: Barang telah diterima oleh pelanggan.',
            'data' => $do
        ]);
    });
    }

    private function processShipping($do, $item)
    {
    $qty = $item['qty'] ?? $item['qty_realisasi'];
    $soItemId = $item['sales_order_item_id'];

    $product = Product::find($item['product_id']);
    
    if ($product->stock < $item['qty']) {
        throw new \Exception("Stok untuk produk {$product->name} tidak mencukupi!");
    }

    // 1. Kurangi Stok Fisik di tabel Products
    Product::where('id', $item['product_id'])->decrement('stock', $qty);

    // 2. Tambah Qty Terkirim di Sales Order Item
    SalesOrderItem::where('id', $soItemId)->increment('qty_shipped', $qty);

    // 3. Catat Mutasi Stok
    StockMovement::create([
        'product_id'   => $item['product_id'],
        'warehouse_id' => $do->warehouse_id,
        'type'         => 'out',
        'quantity'     => $qty,
        'reference_id' => $do->no_sj,
        'notes'        => 'Pengiriman SJ: ' . $do->no_sj,
        'created_by'   => Auth::id() ?? 1,
    ]);
    }

    private function reverseShipping($do, $item)
    {
        Product::where('id', $item->product_id)->increment('stock', $item->qty_realisasi);
        SalesOrderItem::where('id', $item->sales_order_item_id)->decrement('qty_shipped', $item->qty_realisasi);

        StockMovement::create([
            'product_id'   => $item->product_id,
            'warehouse_id' => $do->warehouse_id,
            'type'         => 'IN',
            'quantity'     => $item->qty_realisasi,
            'reference_id' => $do->no_sj,
            'notes'        => 'Pembatalan/Hapus SJ: ' . $do->no_sj,
            'created_by'   => Auth::id() ?? 1,
        ]);
    }
}