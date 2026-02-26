<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryOrder;
use App\Models\StockMovement;
use App\Models\DeliveryAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class DeliveryOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = DeliveryOrder::with(['customer', 'warehouse']);

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
            'delivery_assignment_id' => 'required|exists:delivery_assignments,id',
            'tanggal'    => 'required|date',
            'customer_id'   => 'required|exists:customers,id',
            'warehouse_id'  => 'required|exists:warehouses,id',
            'status'        => 'required|in:draft,shipped',
            'items'         => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty'        => 'required|numeric|min:0.1',
        ]);

        return DB::transaction(function () use ($request) {
            $spkp = DeliveryAssignment::with(['workOrder.salesOrder.items', 'workOrder.salesOrder.customer'])->find($request->delivery_assignment_id);
            // 1. Generate No Dokumen Otomatis
            $noSj = 'SJ-' . date('Ymd') . '-' . strtoupper(Str::random(4));

            // 2. Simpan Header (Data Dokumen)
            $do = DeliveryOrder::create([
                'no_sj'                  => $noSj,
                'delivery_assignment_id' => $request->delivery_assignment_id,
                'tanggal'             => $request->tanggal,
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
                'product_id'    => $item['product_id'],
                'qty_realisasi' => $item['qty'],
            ]);

            if ($request->status === 'shipped') {
                $this->reduceStock($do, $item['product_id'], $item['qty']);
            }
        }

            $assignmentStatus = ($request->status === 'shipped') ? 'in_transit' : 'on_process';
            DeliveryAssignment::where('id', $request->delivery_assignment_id)
                ->update(['status' => $assignmentStatus]);
            
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

        DeliveryAssignment::where('id', $do->delivery_assignment_id)
            ->update(['status' => 'in_transit']);

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
        $do = DeliveryOrder::with(['customer', 'warehouse', 'items.product', 'creator'])->find($id);

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
            // 1. Kembalikan stok yang sudah terpotong (Stock In balik)
            foreach ($do->items as $item) {
                StockMovement::create([
                    'product_id'   => $item->product_id,
                    'warehouse_id' => $do->warehouse_id,
                    'type'         => 'in', // Masuk kembali karena pembatalan SJ
                    'quantity'     => $item->qty_realisasi,
                    'reference_id' => $do->no_sj,
                    'notes'        => 'Pembatalan/Hapus SJ: ' . $do->no_sj,
                    'created_by'   => Auth::id() ?? 1,
                ]);
            }

            // 2. Soft Delete Header SJ
            $do->delete();

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
            // 1. Potong stok lagi (Stock Out) karena SJ aktif kembali
            foreach ($do->items as $item) {
                StockMovement::create([
                    'product_id'   => $item->product_id,
                    'warehouse_id' => $do->warehouse_id,
                    'type'         => 'out',
                    'quantity'     => $item->qty_realisasi,
                    'reference_id' => $do->no_sj,
                    'notes'        => 'Restore SJ: ' . $do->no_sj,
                    'created_by'   => Auth::id() ?? 1,
                ]);
            }

            // 2. Restore data
            $do->restore();

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
            return response()->json(['message' => 'Barang belum dikirim, tidak bisa konfirmasi terima.'], 422);
        }

            // Update status Assignment menjadi completed
        DeliveryAssignment::where('id', $do->delivery_assignment_id)
            ->update(['status' => 'completed']);

        return response()->json([
            'success' => true,
            'message' => 'Barang telah diterima oleh customer. Tugas selesai!'
        ]);
    }
}