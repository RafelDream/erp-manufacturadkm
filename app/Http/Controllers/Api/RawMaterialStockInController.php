<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RawMaterialStock;
use App\Models\RawMaterialStockIn;
use App\Models\RawMaterialStockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RawMaterialStockInController extends Controller
{
    /**
     * GET: Daftar dokumen masuk
     */
    public function index()
    {
        return response()->json(
            RawMaterialStockIn::with(['warehouse', 'items.rawMaterial'])
                ->latest()
                ->paginate(10)
        );
    }

    /**
     * GET: Detail dokumen
     */
    public function show($id)
    {
        return response()->json(
            RawMaterialStockIn::with(['warehouse', 'items.rawMaterial'])
                ->findOrFail($id)
        );
    }

    /**
     * POST: Buat Draft Barang Masuk
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'stock_in_date'           => 'required|date',
            'warehouse_id'            => 'required|exists:warehouses,id',
            'items'                   => 'required|array|min:1',
            'items.*.raw_material_id' => 'required|exists:raw_materials,id',
            'items.*.quantity'        => 'required|numeric|min:0.01',
        ]);

        return DB::transaction(function () use ($validated) {

            $stockIn = RawMaterialStockIn::create([
                'stock_in_number' => 'RMI-' . now()->format('YmdHis'),
                'stock_in_date'   => $validated['stock_in_date'],
                'warehouse_id'    => $validated['warehouse_id'],
                'status'          => 'draft',
                'created_by'      => Auth::id(),
            ]);

            foreach ($validated['items'] as $item) {
                $stockIn->items()->create($item);
            }

            return response()->json([
                'message' => 'Draft barang masuk berhasil dibuat',
                'data'    => $stockIn->load('items.rawMaterial')
            ], 201);
        });
    }

    /**
     * POST: Posting Dokumen (Update Stok + Movement)
     */
    public function post($id)
    {
        $stockIn = RawMaterialStockIn::with('items')->findOrFail($id);

        if ($stockIn->status === 'posted') {
            return response()->json([
                'message' => 'Dokumen sudah diposting'
            ], 422);
        }

        DB::transaction(function () use ($stockIn) {

            foreach ($stockIn->items as $item) {

                $stock = RawMaterialStock::firstOrCreate(
                    [
                        'raw_material_id' => $item->raw_material_id,
                        'warehouse_id'    => $stockIn->warehouse_id,
                    ],
                    ['quantity' => 0]
                );

                $stock->increment('quantity', $item->quantity);

                RawMaterialStockMovement::create([
                    'raw_material_id' => $item->raw_material_id,
                    'warehouse_id'    => $stockIn->warehouse_id,
                    'movement_type'   => 'IN',
                    'quantity'        => $item->quantity,
                    'reference_type'  => RawMaterialStockIn::class,
                    'reference_id'    => $stockIn->id,
                    'created_by'      => Auth::id(),
                ]);
            }

            $stockIn->update(['status' => 'posted']);
        });

        return response()->json([
            'message' => 'Stok berhasil diposting'
        ]);
    }

    /**
     * PUT: Update Draft
     */
    public function update(Request $request, $id)
    {
        $stockIn = RawMaterialStockIn::findOrFail($id);

        if ($stockIn->status === 'posted') {
            return response()->json([
                'message' => 'Dokumen posted tidak bisa diubah'
            ], 422);
        }

        $validated = $request->validate([
            'stock_in_date' => 'sometimes|date',
            'warehouse_id'  => 'sometimes|exists:warehouses,id',
            'notes'         => 'nullable|string',
            'items'         => 'sometimes|array|min:1',
            'items.*.raw_material_id' => 'required_with:items|exists:raw_materials,id',
            'items.*.quantity'        => 'required_with:items|numeric|min:0.01',
        ]);

        return DB::transaction(function () use ($validated, $stockIn) {
        $stockIn->update($validated);

        // Jika frontend mengirimkan array items baru, hapus yang lama dan buat baru (cara termudah untuk draft)
        if (isset($validated['items'])) {
            $stockIn->items()->delete(); 
            foreach ($validated['items'] as $item) {
                $stockIn->items()->create($item);
            }
        }

        return response()->json([
            'message' => 'Draft berhasil diperbarui',
            'data'    => $stockIn->load('items.rawMaterial')
        ]);
    });
    }   

    /**
     * DELETE: Soft Delete
     */
    public function destroy($id)
    {
        $stockIn = RawMaterialStockIn::findOrFail($id);

        if ($stockIn->status === 'posted') {
            return response()->json([
                'message' => 'Dokumen posted tidak bisa dihapus'
            ], 422);
        }

        $stockIn->delete();

        return response()->json([
            'message' => 'Dokumen berhasil dihapus'
        ]);
    }

    /**
     * POST: Restore
     */
    public function restore($id)
    {
        RawMaterialStockIn::onlyTrashed()
            ->findOrFail($id)
            ->restore();

        return response()->json([
            'message' => 'Dokumen berhasil direstore'
        ]);
    }
}

