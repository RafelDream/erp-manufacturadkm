<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductionOrder;
use App\Models\ProductionExecution;
use App\Models\RawMaterialStock;
use App\Models\RawMaterialStockMovement;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductionExecutionController extends Controller
{
    /**
     * POST - Start Production (consume materials)
     */
    public function start(Request $request, $productionOrderId)
    {
        $po = ProductionOrder::with('bom.items.rawMaterial')->findOrFail($productionOrderId);

        if ($po->status !== 'released') {
            return response()->json([
                'message' => 'Production order harus released terlebih dahulu'
            ], 422);
        }

        $validated = $request->validate([
            'started_at' => 'required|date',
            'operator' => 'required|string',
        ]);

        try {
            DB::transaction(function () use ($po, $validated) {
                // Calculate and consume materials
                $batchMultiplier = $po->quantity_plan / $po->bom->batch_size;
                $totalMaterialCost = 0;

                foreach ($po->bom->items as $bomItem) {
                    $requiredQty = $bomItem->quantity * $batchMultiplier;
                    
                    // Get stock
                    $stock = RawMaterialStock::where('raw_material_id', $bomItem->raw_material_id)
                        ->where('warehouse_id', $po->warehouse_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    // Deduct stock
                    $stock->decrement('quantity', $requiredQty);

                    // Record material usage
                    $materialCost = $requiredQty * ($bomItem->rawMaterial->last_purchase_price ?? 0);
                    $totalMaterialCost += $materialCost;

                    $po->materialUsages()->create([
                        'raw_material_id' => $bomItem->raw_material_id,
                        'quantity_used' => $requiredQty,
                        'unit_cost' => $bomItem->rawMaterial->last_purchase_price ?? 0,
                        'total_cost' => $materialCost,
                    ]);

                    // Record stock movement
                    RawMaterialStockMovement::create([
                        'raw_material_id' => $bomItem->raw_material_id,
                        'warehouse_id' => $po->warehouse_id,
                        'movement_type' => 'OUT',
                        'quantity' => $requiredQty,
                        'reference_type' => ProductionOrder::class,
                        'reference_id' => $po->id,
                        'notes' => 'Material untuk produksi ' . $po->production_number,
                        'created_by' => Auth::id(),
                    ]);
                }

                // Update PO status
                $po->update([
                    'status' => 'in_progress',
                    'started_at' => $validated['started_at'],
                    'started_by' => Auth::id(),
                    'operator' => $validated['operator'],
                    'total_material_cost' => $totalMaterialCost,
                ]);
            });

            return response()->json([
                'message' => 'Produksi dimulai, material berhasil dikonsumsi'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal memulai produksi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST - Complete Production (add finished goods + calculate HPP)
     */
    public function complete(Request $request, $productionOrderId)
    {
        $po = ProductionOrder::with('product')->findOrFail($productionOrderId);

        if ($po->status !== 'in_progress') {
            return response()->json([
                'message' => 'Production order harus in_progress'
            ], 422);
        }

        $validated = $request->validate([
            'quantity_actual' => 'required|numeric|min:0.001',
            'quantity_waste' => 'nullable|numeric|min:0',
            'completed_at' => 'required|date',
            'labor_cost' => 'nullable|numeric|min:0',
            'overhead_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::transaction(function () use ($po, $validated) {
                // Calculate HPP
                $materialCost = $po->total_material_cost;
                $laborCost = $validated['labor_cost'] ?? 0;
                $overheadCost = $validated['overhead_cost'] ?? 0;
                
                $totalProductionCost = $materialCost + $laborCost + $overheadCost;
                $hppPerUnit = $totalProductionCost / $validated['quantity_actual'];

                // Update Production Order
                $po->update([
                    'quantity_actual' => $validated['quantity_actual'],
                    'quantity_waste' => $validated['quantity_waste'] ?? 0,
                    'labor_cost' => $laborCost,
                    'overhead_cost' => $overheadCost,
                    'total_production_cost' => $totalProductionCost,
                    'hpp_per_unit' => $hppPerUnit,
                    'status' => 'completed',
                    'completed_at' => $validated['completed_at'],
                    'completed_by' => Auth::id(),
                    'completion_notes' => $validated['notes'] ?? null,
                ]);

                // Add finished goods to stock
                $stock = Stock::firstOrCreate(
                    [
                        'product_id' => $po->product_id,
                        'warehouse_id' => $po->warehouse_id,
                    ],
                    ['quantity' => 0]
                );

                $stock->increment('quantity', $validated['quantity_actual']);

                // Record stock movement
                StockMovement::create([
                    'product_id' => $po->product_id,
                    'warehouse_id' => $po->warehouse_id,
                    'type' => 'in',
                    'quantity' => $validated['quantity_actual'],
                    'reference_type' => 'production',
                    'reference_id' => $po->id,
                    'notes' => 'Hasil produksi ' . $po->production_number . ' | HPP: ' . number_format($hppPerUnit, 2),
                    'created_by' => Auth::id(),
                ]);
            });

            return response()->json([
                'message' => 'Produksi selesai, barang jadi berhasil ditambahkan ke stock'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menyelesaikan produksi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET - Production Report (HPP Detail)
     */
    public function getReport($productionOrderId)
    {
        $po = ProductionOrder::with([
            'product',
            'bom',
            'materialUsages.rawMaterial'
        ])->findOrFail($productionOrderId);

        if ($po->status !== 'completed') {
            return response()->json([
                'message' => 'Production order belum completed'
            ], 422);
        }

        return response()->json([
            'production_number' => $po->production_number,
            'product' => $po->product->name,
            'production_date' => $po->production_date,
            
            // Quantity
            'quantity_plan' => $po->quantity_plan,
            'quantity_actual' => $po->quantity_actual,
            'quantity_waste' => $po->quantity_waste,
            'efficiency' => ($po->quantity_actual / $po->quantity_plan) * 100,
            
            // Cost Breakdown
            'material_cost' => $po->total_material_cost,
            'labor_cost' => $po->labor_cost,
            'overhead_cost' => $po->overhead_cost,
            'total_production_cost' => $po->total_production_cost,
            
            // HPP
            'hpp_per_unit' => $po->hpp_per_unit,
            
            // Material Details
            'material_usage' => $po->materialUsages->map(function ($usage) {
                return [
                    'material' => $usage->rawMaterial->name,
                    'quantity_used' => $usage->quantity_used,
                    'unit_cost' => $usage->unit_cost,
                    'total_cost' => $usage->total_cost,
                ];
            }),
            
            'operator' => $po->operator,
            'started_at' => $po->started_at,
            'completed_at' => $po->completed_at,
        ]);
    }

    /**
     * GET - List all production executions
     */
    public function index(Request $request)
    {
        $query = ProductionOrder::with(['product', 'warehouse'])
            ->whereIn('status', ['in_progress', 'completed']);

        // Filter by date range
        if ($request->start_date && $request->end_date) {
            $query->whereBetween('completed_at', [$request->start_date, $request->end_date]);
        }

        return response()->json($query->latest('completed_at')->get());
    }
}