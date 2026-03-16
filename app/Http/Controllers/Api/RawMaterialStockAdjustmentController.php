<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RawMaterialStock;
use App\Models\RawMaterialStockAdjustment;
use App\Models\RawMaterialStockMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class RawMaterialStockAdjustmentController extends Controller
{
    public function index()
    {
        return response()->json(
            RawMaterialStockAdjustment::with(['rawMaterial', 'warehouse'])->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'raw_material_id' => 'required|exists:raw_materials,id',
            'warehouse_id'    => 'required|exists:warehouses,id',
            'after_quantity'  => 'required|numeric|min:0',
            'reason'          => 'nullable|string',
        ]);

        $result = DB::transaction(function () use ($validated) {

            $stock = RawMaterialStock::firstOrCreate([
                'raw_material_id' => $validated['raw_material_id'],
                'warehouse_id'    => $validated['warehouse_id'],
            ], ['quantity' => 0]);

            $before = $stock->quantity;
            $after  = $validated['after_quantity'];
            $diff   = $after - $before;

            $adjustment_no = 'ADJ-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));

            $adjustment = RawMaterialStockAdjustment::create([
                'adjustment_no'   => $adjustment_no,
                'raw_material_id' => $validated['raw_material_id'],
                'warehouse_id'    => $validated['warehouse_id'],
                'before_quantity' => $before,
                'after_quantity'  => $after,
                'difference'      => $diff,
                'type'            => ($diff >= 0) ? 'in' : 'out',
                'reason'          => $validated['reason'] ?? null,
                'created_by'      => Auth::id(),
            ]);

            // update stok
            $stock->update(['quantity' => $after]);

            // movement log
            RawMaterialStockMovement::create([
                'raw_material_id' => $validated['raw_material_id'],
                'warehouse_id'    => $validated['warehouse_id'],
                'movement_type'   => 'ADJUSTMENT',
                'quantity'        => abs($diff),
                'reference_type'  => RawMaterialStockAdjustment::class,
                'reference_id'    => $adjustment->id,
                'created_by'      => Auth::id(),
            ]);
            return $adjustment->load(['rawMaterial', 'warehouse']);
        });
        
        return response()->json([
                'success' => true,
                'message' => 'Raw Material Stock adjustment berhasil dibuat',
                'data'    => $result
            ], 201);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
        'after_quantity' => 'required|numeric|min:0',
        'reason'    => 'nullable|string',
        ]);

        $adjustment = RawMaterialStockAdjustment::findOrFail($id);

        $newDifference = $validated['after_quantity'] - $adjustment->before_quantity;

        // update data adjustment
        $adjustment->update([
        'after_quantity' => $validated['after_quantity'],
        'difference'     => $newDifference,
        'reason'    => $validated['reason'] ?? $adjustment->reason,
        ]);

        return response()->json([
        'success' => true,
        'message' => 'Raw Material Stock Adjustment berhasil diperbarui',
        'data'    => $adjustment
        ]);
    }

    public function destroy($id)
    {
            return DB::transaction(function () use ($id) {
            $adjustment = RawMaterialStockAdjustment::withTrashed()->findOrFail($id);
        
            // KOREKSI STOK BALIK: Kembalikan quantity ke 'before_quantity'
            $stock = RawMaterialStock::where([
                'raw_material_id' => $adjustment->raw_material_id,
                'warehouse_id'    => $adjustment->warehouse_id,
            ])->first();

            if ($stock) {
                $stock->update(['quantity' => $adjustment->before_quantity]);
            }

            // Hapus log movement terkait agar laporan stok tetap akurat
            RawMaterialStockMovement::where('reference_type', RawMaterialStockAdjustment::class)
                ->where('reference_id', $adjustment->id)
                ->delete();

            $adjustment->delete();

            return response()->json(['success' => true, 'message' => 'Adjustment dibatalkan dan stok dikembalikan ke awal']);
        });
    }

    public function restore($id)
    {
        RawMaterialStockAdjustment::withTrashed()->findOrFail($id)->restore();

        return response()->json(['message' => 'Raw Material Stock Adjustment dipulihkan']);
    }
}
