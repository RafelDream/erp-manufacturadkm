<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RawMaterialStock;
use App\Models\RawMaterialStockOut;
use App\Models\RawMaterialStockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RawMaterialStockOutController extends Controller
{
    /**
     * Simpan transaksi ke tabel Header dan Detail (Status: Draft)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'issued_at'    => 'required|date',
            'notes'        => 'nullable|string',
            'items'        => 'required|array|min:1',
            'items.*.raw_material_id' => 'required|exists:raw_materials,id',
            'items.*.quantity'        => 'required|numeric|min:0.001',
            'items.*.unit_id'         => 'required|exists:units,id',
        ]);

        try {
            $stockOut = DB::transaction(function () use ($validated) {
                // 1. Simpan Header
                $header = RawMaterialStockOut::create([
                    'warehouse_id' => $validated['warehouse_id'],
                    'issued_at'    => $validated['issued_at'],
                    'status'       => 'draft',
                    'created_by'   => Auth::id(),
                    'notes'        => $validated['notes'] ?? null,
                ]);

                // 2. Simpan Detail Items sekaligus
                $header->items()->createMany($validated['items']);

                return $header; 
            });

            return response()->json([
                'message' => 'Draft barang keluar berhasil dibuat',
                'data' => $stockOut->load('items')
            ], 201);
        
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal Membuat Draft', 
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Posting transaksi: Validasi stok, potong saldo, dan catat mutasi
     */
    public function post($id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $stockOut = RawMaterialStockOut::with('items.rawMaterial')->findOrFail($id);

                if ($stockOut->status !== 'draft') {
                    return response()->json(['message' => 'Dokumen Berhasil di Proses'], 400);
                }
    
                foreach ($stockOut->items as $item) {
                    // Ambil stok dengan lock untuk keamanan data
                    $stock = RawMaterialStock::where('raw_material_id', $item->raw_material_id)
                        ->where('warehouse_id', $stockOut->warehouse_id)
                        ->lockForUpdate()
                        ->first();

                // Cek ketersediaan saldo
                    if (!$stock || $stock->quantity < $item->quantity) {
                        throw new \Exception("Stok tidak cukup untuk: " . ($item->rawMaterial->name ?? 'Material ID ' . $item->raw_material_id));
                    }

                    // Update Saldo Utama
                    $stock->decrement('quantity', $item->quantity);

                // Catat riwayat mutasi (Tersambung dengan Stock In melalui tabel ini)
                    RawMaterialStockMovement::create([
                        'raw_material_id' => $item->raw_material_id,
                        'warehouse_id'    => $stockOut->warehouse_id,
                        'movement_type'   => 'OUT',
                        'quantity'        => $item->quantity,
                        'reference_type'  => get_class($stockOut),
                        'reference_id'    => $stockOut->id,
                        'created_by'      => Auth::id(),
                    ]);
            }

            $stockOut->update(['status' => 'posted']);

            return response()->json(['message' => 'Data Barang Keluar sudah terkirim']);
        });
    } catch (\Exception $e) {
            return response()->json([
                'message' => 'Posting Gagal', 
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * GET - List semua riwayat stok keluar
     */
    public function index()
    {
        $data = RawMaterialStockOut::with(['warehouse', 'creator'])->latest()->get();
        return response()->json($data);
    }

    /**
     * GET - Detail stok keluar beserta item barangnya
     */
    public function show($id)
    {
        $stockOut = RawMaterialStockOut::with(['warehouse', 'creator', 'items.rawMaterial', 'items.unit'])->findOrFail($id);
        return response()->json($stockOut);
    }

    /**
     * PUT - Update data (Hanya jika status masih draft)
     */
    public function update(Request $request, $id)
    {
        $stockOut = RawMaterialStockOut::findOrFail($id);
    
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'issued_at'    => 'required|date',
            'notes'        => 'nullable|string',
        ]);


        if ($stockOut->status !== 'draft') {
            return response()->json(['message' => 'Gagal Update. Dokumen sudah dikirim'], 400);
        }

        $stockOut->update($validated);

        if ($request->has('items')) {
            $stockOut->items()->delete(); // Hapus item lama
            $stockOut->items()->createMany($request->items); // Masukkan item baru yang sudah diedit
        }
        return response()->json(['message' => 'Data Barang Keluar berhasil di Update', 'data' => $stockOut]);
    }

    /**
     * DELETE - Menghapus data (Soft Delete)
     */
    public function destroy($id)
    {
        $stockOut = RawMaterialStockOut::findOrFail($id);
        
        if ($stockOut->status === 'posted') {
            return response()->json(['message' => 'Gagal Hapus. Dokumen sudah dikirim'], 400);
        }

        $stockOut->delete();
        return response()->json(['message' => 'Data Barang Keluar berhasil di Hapus']);
    }

    /**
     * POST - Mengembalikan data yang dihapus
     */
    public function restore($id)
    {
        RawMaterialStockOut::withTrashed()->findOrFail($id)->restore();
        return response()->json(['message' => 'Data Barang Keluar berhasil di Kembalikan']);
    }
}