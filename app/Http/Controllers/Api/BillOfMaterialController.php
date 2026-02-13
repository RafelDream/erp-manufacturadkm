<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BillOfMaterial;
use App\Models\Product;
use App\Models\RawMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BillOfMaterialController extends Controller
{
    /**
     * GET - List BOM
     */
    public function index(Request $request)
    {
        $query = BillOfMaterial::with([
            'product',
            'items.rawMaterial',
            'items.unit',
            'creator'
        ]);

        // Filter by product
        if ($request->product_id) {
            $query->where('product_id', $request->product_id);
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return response()->json($query->latest()->get());
    }

    /**
     * GET - Detail BOM
     */
    public function show($id)
    {
        return response()->json(
            BillOfMaterial::with([
                'product',
                'items.rawMaterial',
                'items.unit',
                'creator'
            ])->findOrFail($id)
        );
    }

    /**
     * POST - Create BOM
     */
    public function store(Request $request)
    {

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'batch_size' => 'required|numeric|min:1',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.raw_material_id' => 'required|exists:raw_materials,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit_id' => 'exists:units,id',
        ]);

        try {
            $bom = DB::transaction(function () use ($validated) {

                $product = Product::findOrFail($validated['product_id']);

                $bom = BillOfMaterial::create([
                    'bom_number' => 'BOM-' . now()->format('YmdHis'),
                    'product_id' => $validated['product_id'],
                    'batch_size' => $validated['batch_size'],
                    'unit_id'    => $product->unit_id,
                    'notes'      => $validated['notes'] ?? null,
                    'is_active'  => true,
                    'created_by' => Auth::id(),
                ]);

                foreach ($validated['items'] as $item) {
                    
                    $material = RawMaterial::findOrFail($item['raw_material_id']);

                    $bom->items()->create([
                        'raw_material_id' => $item['raw_material_id'],
                        'quantity'        => $item['quantity'],
                        'unit_id'         => $item['unit_id'] ?? $material->unit_id,
                    ]);
                }

                return $bom;
            });

            return response()->json([
                'message' => 'BOM berhasil dibuat',
                'data' => $bom->load('items.rawMaterial', 'items.unit')
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal membuat BOM',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT - Update BOM
     */
    public function update(Request $request, $id)
    {
        $bom = BillOfMaterial::findOrFail($id);

        $validated = $request->validate([
            'batch_size' => 'required|numeric|min:1',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $bom->update($validated);

        return response()->json([
            'message' => 'BOM berhasil diupdate'
        ]);
    }

    /**
     * PUT - Update BOM Item
     */
    public function updateItem(Request $request, $bomId, $itemId)
    {
        $bom = BillOfMaterial::findOrFail($bomId);
        $item = $bom->items()->findOrFail($itemId);

        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0.001',
        ]);

        $item->update($validated);

        return response()->json([
            'message' => 'BOM item berhasil diupdate'
        ]);
    }

    /**
     * DELETE - Delete BOM
     */
    public function destroy($id)
    {
        $bom = BillOfMaterial::findOrFail($id);
        $bom->delete();

        return response()->json([
            'message' => 'BOM berhasil dihapus'
        ]);
    }

    /**
     * POST - Restore BOM
     */
    public function restore($id)
    {
        BillOfMaterial::withTrashed()->findOrFail($id)->restore();

        return response()->json([
            'message' => 'BOM berhasil direstore'
        ]);
    }

    /**
     * GET - Calculate material cost for BOM
     */
    public function calculateCost($id)
    {
        $bom = BillOfMaterial::with('items.rawMaterial')->findOrFail($id);

        $totalCost = 0;
        $details = [];

        foreach ($bom->items as $item) {
            $cost = $item->quantity * ($item->rawMaterial->last_purchase_price ?? 0);
            $totalCost += $cost;

            $details[] = [
                'raw_material' => $item->rawMaterial->name,
                'quantity' => $item->quantity,
                'unit_price' => $item->rawMaterial->last_purchase_price ?? 0,
                'total_cost' => $cost,
            ];
        }

        return response()->json([
            'bom_number' => $bom->bom_number,
            'product' => $bom->product->name,
            'batch_size' => $bom->batch_size,
            'material_cost_per_batch' => $totalCost,
            'material_cost_per_unit' => $totalCost / $bom->batch_size,
            'details' => $details,
        ]);
    }
}