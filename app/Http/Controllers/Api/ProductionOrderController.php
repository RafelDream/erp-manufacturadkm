<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductionOrder;
use App\Models\BillOfMaterial;
use App\Models\RawMaterialStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductionOrderController extends Controller
{
    /**
     * GET - List Production Orders
     */
    public function index(Request $request)
    {
        $query = ProductionOrder::with([
            'product',
            'bom',
            'warehouse',
            'creator'
        ]);

        // Filter by date range
        if ($request->start_date && $request->end_date) {
            $query->whereBetween('production_date', [$request->start_date, $request->end_date]);
        }

        // Filter by status
        if ($request->status) {
            $query->where('status', $request->status);
        }

        return response()->json($query->latest()->get());
    }

    /**
     * GET - Detail Production Order
     */
    public function show($id)
    {
        return response()->json(
            ProductionOrder::with([
                'product',
                'bom.items.rawMaterial',
                'warehouse',
                'creator',
                'executor',
                'materialUsages.rawMaterial'
            ])->findOrFail($id)
        );
    }

    /**
     * POST - Create Production Order
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'bom_id' => 'required|exists:bill_of_materials,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'production_date' => 'required|date',
            'quantity_plan' => 'required|numeric|min:1',
            'notes' => 'nullable|string',
        ]);

        // Validasi BOM cocok dengan product
        $bom = BillOfMaterial::findOrFail($validated['bom_id']);
        
        if ($bom->product_id != $validated['product_id']) {
            return response()->json([
                'message' => 'BOM tidak sesuai dengan produk yang dipilih'
            ], 422);
        }

        try {
            $po = ProductionOrder::create([
                'production_number' => 'PRO-' . now()->format('YmdHis'),
                'product_id' => $validated['product_id'],
                'bom_id' => $validated['bom_id'],
                'warehouse_id' => $validated['warehouse_id'],
                'production_date' => $validated['production_date'],
                'quantity_plan' => $validated['quantity_plan'],
                'status' => 'draft',
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            return response()->json([
                'message' => 'Production Order berhasil dibuat',
                'data' => $po->load('product', 'bom')
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal membuat Production Order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT - Update Production Order (draft only)
     */
    public function update(Request $request, $id)
    {
        $po = ProductionOrder::findOrFail($id);

        if ($po->status !== 'draft') {
            return response()->json([
                'message' => 'Hanya production order draft yang bisa diupdate'
            ], 422);
        }

        $validated = $request->validate([
            'production_date' => 'required|date',
            'quantity_plan' => 'required|numeric|min:1',
            'notes' => 'nullable|string',
        ]);

        $po->update($validated);

        return response()->json([
            'message' => 'Production Order berhasil diupdate'
        ]);
    }

    /**
     * POST - Release Production Order
     */
    public function release($id)
    {
        $po = ProductionOrder::with('bom.items')->findOrFail($id);

        if ($po->status !== 'draft') {
            return response()->json([
                'message' => 'Hanya production order draft yang bisa direlease'
            ], 422);
        }

        // Calculate required materials
        $batchMultiplier = $po->quantity_plan / $po->bom->batch_size;
        $insufficientMaterials = [];

        foreach ($po->bom->items as $bomItem) {
            $requiredQty = $bomItem->quantity * $batchMultiplier;
            
            $stock = RawMaterialStock::where('raw_material_id', $bomItem->raw_material_id)
                ->where('warehouse_id', $po->warehouse_id)
                ->first();

            $availableQty = $stock->quantity ?? 0;

            if ($availableQty < $requiredQty) {
                $insufficientMaterials[] = [
                    'material' => $bomItem->rawMaterial->name,
                    'required' => $requiredQty,
                    'available' => $availableQty,
                    'shortage' => $requiredQty - $availableQty,
                ];
            }
        }

        if (count($insufficientMaterials) > 0) {
            return response()->json([
                'message' => 'Material tidak mencukupi',
                'insufficient_materials' => $insufficientMaterials
            ], 422);
        }

        $po->update([
            'status' => 'released',
            'released_by' => Auth::id(),
            'released_at' => now(),
        ]);

        return response()->json([
            'message' => 'Production Order berhasil direlease, siap produksi'
        ]);
    }

    /**
     * DELETE - Cancel Production Order
     */
    public function destroy($id)
    {
        $po = ProductionOrder::findOrFail($id);

        if (in_array($po->status, ['in_progress', 'completed'])) {
            return response()->json([
                'message' => 'Production order yang sedang berjalan atau selesai tidak bisa dihapus'
            ], 422);
        }

        $po->delete();

        return response()->json([
            'message' => 'Production Order berhasil dihapus'
        ]);
    }

    /**
     * POST - Restore Production Order
     */
    public function restore($id)
    {
        ProductionOrder::withTrashed()->findOrFail($id)->restore();

        return response()->json([
            'message' => 'Production Order berhasil direstore'
        ]);
    }
}