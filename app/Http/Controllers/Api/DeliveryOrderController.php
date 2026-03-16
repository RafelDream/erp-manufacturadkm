<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryOrder;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Stock;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class DeliveryOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = DeliveryOrder::with(['customer', 'warehouse', 'salesOrder']);

        if ($request->filled(['tanggal_awal', 'tanggal_akhir'])) {
            $query->whereBetween('tanggal', [$request->tanggal_awal, $request->tanggal_akhir]);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
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
            'expedition'    => 'nullable|string',
            'vehicle_number' => 'nullable|string',
             'vehicle_number' => 'nullable|string',
            'items'         => 'required|array|min:1',
            'items.*.sales_order_item_id' => 'required|exists:sales_order_items,id',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty'        => 'required|numeric|min:0.1',
        ]);

        return DB::transaction(function () use ($request) {

            $so = SalesOrder::with('items')->findOrFail($request->sales_order_id);
            if (!in_array($so->status, ['approved', 'partial'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'SPK tidak dalam status yang bisa dibuatkan surat jalan. Status saat ini: ' . $so->status,
                ], 422);
            }

             if ($request->status === 'shipped') {
                foreach ($request->items as $item) {
                    $this->validateStock(
                        productId:   $item['product_id'],
                        warehouseId: $request->warehouse_id,
                        qty:         $item['qty']
                    );

                    $this->validateQtyRemaining(
                        soItemId: $item['sales_order_item_id'],
                        qty:      $item['qty']
                    );
                }
            }

            // Generate No Dokumen Otomatis
            $noSj = 'SJ-' . date('Ymd') . '-' . strtoupper(Str::random(4));

            //Simpan Header (Data Dokumen)
            $do = DeliveryOrder::create([
                'no_sj'                  => $noSj,
                'sales_order_id'         => $request->sales_order_id,
                'tanggal'                => $request->tanggal,
                'no_spk'                 => $request->no_spk,
                'customer_id'            => $request->customer_id,
                'warehouse_id'           => $request->warehouse_id,
                'expedition'             => $request->expedition,
                'vehicle_number'         => $request->vehicle_number,
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
                $this->executeShipping($do);
                $so->load('items'); // reload agar syncStatus hitung ulang
                $so->syncStatus();
            }
        }
            $do->salesOrder->syncStatus();

            return response()->json([
                'success' => true,
                'message' => $request->status === 'shipped'
                    ? 'Surat Jalan berhasil dibuat & stok otomatis terpotong.'
                    : 'Surat Jalan berhasil disimpan sebagai draft.',
                'data'    => $do->load('items.product', 'customer', 'warehouse'),
            ], 201);
        });
    }

    public function sendOrder($id)
    {
    $do = DeliveryOrder::with(['items', 'salesOrder.items'])->findOrFail($id);

    if ($do->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya surat jalan berstatus draft yang bisa dikirim. Status saat ini: ' . $do->status,
            ], 422);
        }

    return DB::transaction(function () use ($do) {
        foreach ($do->items as $item) {
                $this->validateStock(
                    productId:   $item->product_id,
                    warehouseId: $do->warehouse_id,
                    qty:         $item->qty_realisasi
                );

                $this->validateQtyRemaining(
                    soItemId: $item->sales_order_item_id,
                    qty:      $item->qty_realisasi
                );
            }

            $this->executeShipping($do);

        $do->update(['status' => 'shipped']);

        $do->salesOrder->load('items');
        $do->salesOrder->syncStatus();

        return response()->json([
                'success' => true,
                'message' => 'Surat jalan berhasil dikirim. Stok gudang telah terpotong.',
                'data'    => $do->load('items.product'),
            ]);
        });
    }

    /**
     * Menampilkan Detail Surat Jalan
     */
    public function show($id)
    {
        $do = DeliveryOrder::with(['customer', 'warehouse', 'items.product.unit', 'items.salesOrderItem', 'creator', 'salesOrder'])->find($id);

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

        if ($do->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Surat jalan yang sudah dikirim atau diterima tidak bisa diubah.',
            ], 422);
        }

        $request->validate([
            'tanggal'     => 'sometimes|date',
            'expedition'     => 'sometimes|string',
            'vehicle_number' => 'sometimes|string',
            'notes'          => 'sometimes|nullable|string',
        ]);

        $do->update($request->only([
            'tanggal', 'no_spk', 'expedition', 'vehicle_number', 'notes'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Data Surat Jalan berhasil diperbarui',
            'data' => $do->load('items.product', 'customer', 'warehouse'),
        ]);
    }

    /**
     * Hapus Surat Jalan (Soft Delete)
     */
    public function destroy($id)
    {
        $do = DeliveryOrder::with('items', 'salesOrder')->find($id);

        if (!$do) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        if ($do->status === 'received') {
            return response()->json([
                'success' => false,
                'message' => 'Surat jalan yang sudah diterima pelanggan tidak bisa dihapus.',
            ], 422);
        }

        return DB::transaction(function () use ($do) {
            // Jika sudah shipped, kembalikan stok dan kurangi qty_shipped di SO
            if ($do->status === 'shipped') {
                $this->reverseShipping($do);
            }

            $do->delete();
            
            if ($do->salesOrder) {
                $do->salesOrder->load('items');
                $do->salesOrder->syncStatus();
            }

            return response()->json([
                'success' => true,
                'message' => 'Surat Jalan berhasil dihapus.' .
                    ($do->status === 'shipped' ? ' Stok gudang telah dikembalikan.' : ''),
            ]);
        });
    }

    /**
     * Restore Surat Jalan yang dihapus
     */
    public function restore($id)
    {
        
        return DB::transaction(function () use ($id) {
        $do = DeliveryOrder::onlyTrashed()->with(['items', 'salesOrder'])->find($id);

        if (!$do) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan di sampah'], 404);
        }

            $do->restore();

            if ($do->status === 'shipped') {
                foreach ($do->items as $item) {
                    $this->validateStock(
                        productId:   $item->product_id,
                        warehouseId: $do->warehouse_id,
                        qty:         $item->qty_realisasi
                    );
                }
                $this->executeShipping($do);
            }

            if ($do->salesOrder) {
                $do->salesOrder->load('items');
                $do->salesOrder->syncStatus();
            }

            $do->load('items.product');

            return response()->json([
                'success' => true,
                'message' => 'Data Surat Jalan Berhasil Dikembalikan',
                'data' => $do,
            ]);
        });
    }

        public function confirmReceived($id)
    {
        $do = DeliveryOrder::with('salesOrder')->findOrFail($id);

        if ($do->status !== 'shipped') {
        return response()->json([
            'success' => false,
            'message' => 'Hanya surat jalan berstatus shipped yang bisa dikonfirmasi diterima.'
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
            $do->salesOrder->load('items');
            $do->salesOrder->syncStatus();
        }

        return response()->json([
            'success' => true,
            'message' => 'Konfirmasi berhasil: Barang telah diterima oleh pelanggan.',
            'data' => $do
        ]);
    });
    }

    private function reverseShipping(DeliveryOrder $do): void
    {
        $items = $do->relationLoaded('items') ? $do->items : $do->items()->get();

        foreach ($items as $item) {
            // 1. Kembalikan stok fisik
            Stock::where('product_id', $item->product_id)
                 ->where('warehouse_id', $do->warehouse_id)
                 ->increment('quantity', $item->qty_realisasi);
            // 2. Kurangi qty_shipped di SO item
            SalesOrderItem::where('id', $item->sales_order_item_id)
                          ->decrement('qty_shipped', $item->qty_realisasi);
             // 3. Catat mutasi stok masuk (reversal)
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

    private function validateStock(int $productId, int $warehouseId, float $qty): void
    {
        $stock = Stock::where('product_id', $productId)
                      ->where('warehouse_id', $warehouseId)
                      ->first();
 
        if (!$stock || $stock->quantity < $qty) {
            $productName = Product::find($productId)?->name ?? 'ID: ' . $productId;
            $available   = $stock?->quantity ?? 0;
            throw new \Exception(
                "Stok produk '{$productName}' tidak mencukupi. " .
                "Tersedia: {$available}, dibutuhkan: {$qty}."
            );
        }
    }

    private function validateQtyRemaining(int $soItemId, float $qty): void
    {
        $soItem = SalesOrderItem::findOrFail($soItemId);
 
        if ($qty > $soItem->qty_remaining) {
            $productName = $soItem->product?->name ?? 'ID: ' . $soItem->product_id;
            throw new \Exception(
                "Qty pengiriman produk '{$productName}' melebihi sisa pesanan. " .
                "Sisa: {$soItem->qty_remaining}, dikirim: {$qty}."
            );
        }
    }

     private function executeShipping(DeliveryOrder $do): void
    {
        // Pastikan items sudah ter-load
        $items = $do->relationLoaded('items') ? $do->items : $do->items()->get();
 
        foreach ($items as $item) {
            // Lock row stok agar tidak ada request lain yang baca nilai lama
            $stock = Stock::where('product_id', $item->product_id)
                          ->where('warehouse_id', $do->warehouse_id)
                          ->lockForUpdate()
                          ->first();
 
            if (!$stock) {
                $productName = Product::find($item->product_id)?->name ?? 'ID: ' . $item->product_id;
                throw new \Exception("Record stok untuk produk '{$productName}' tidak ditemukan.");
            }
 
            // 1. Kurangi stok fisik di tabel stocks
            $stock->decrement('quantity', $item->qty_realisasi);
 
            // 2. Tambah qty_shipped di sales_order_items
            SalesOrderItem::where('id', $item->sales_order_item_id)
                          ->increment('qty_shipped', $item->qty_realisasi);
 
            // 3. Catat mutasi stok keluar
            StockMovement::create([
                'product_id'   => $item->product_id,
                'warehouse_id' => $do->warehouse_id,
                'type'         => 'out',
                'quantity'     => $item->qty_realisasi,
                'reference_id' => $do->no_sj,
                'notes'        => 'Pengiriman SJ: ' . $do->no_sj,
                'created_by'   => Auth::id() ?? 1,
            ]);
        }
    }

    public function printPdf($id)
    {
        // Load data lengkap dengan relasinya
        $do = DeliveryOrder::with(['customer', 'warehouse', 'items.product.unit', 'salesOrder', 'creator'])->findOrFail($id);

        // Kirim data ke view blade bernama 'pdf.delivery_order'
        $pdf = Pdf::loadView('deliveryorder.delivery_order', compact('do'))
                ->setPaper('a4', 'portrait');

        // Nama file saat didownload
        $filename = 'SJ-' . $do->no_sj . '.pdf';

        // Stream untuk membuka di browser (atau download() untuk langsung unduh)
        return $pdf->stream($filename);
    }
}