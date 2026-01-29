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

        DB::transaction(function () use ($validated) {

            $stock = RawMaterialStock::where([
                'raw_material_id' => $validated['raw_material_id'],
                'warehouse_id'    => $validated['warehouse_id'],
            ])->firstOrFail();

            $before = $stock->quantity;
            $after  = $validated['after_quantity'];
            $diff   = $after - $before;

            $adjustment = RawMaterialStockAdjustment::create([
                'raw_material_id' => $validated['raw_material_id'],
                'warehouse_id'    => $validated['warehouse_id'],
                'before_quantity' => $before,
                'after_quantity'  => $after,
                'difference'      => $diff,
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
        });

        return response()->json(['message' => 'Raw Material Stock adjustment berhasil dibuat'], 201);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
        'quantity' => 'required|numeric|min:0',
        'notes'    => 'nullable|string',
        ]);

        $adjustment = RawMaterialStockAdjustment::findOrFail($id);

        // update data adjustment
        $adjustment->update([
        'quantity' => $validated['quantity'],
        'notes'    => $validated['notes'] ?? $adjustment->notes,
        ]);

        return response()->json([
        'message' => 'Raw Material Stock Adjustment berhasil diperbarui',
        'data'    => $adjustment
        ]);
    }

    public function destroy($id)
    {
        RawMaterialStockAdjustment::findOrFail($id)->delete();

        return response()->json(['message' => 'Raw Material Stock Adjustment dihapus']);
    }

    public function restore($id)
    {
        RawMaterialStockAdjustment::withTrashed()->findOrFail($id)->restore();

        return response()->json(['message' => 'Raw Material Stock Adjustment dipulihkan']);
    }
}
