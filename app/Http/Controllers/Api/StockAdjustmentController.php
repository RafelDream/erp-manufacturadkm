<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Http\Client\ResponseSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class StockAdjustmentController extends Controller
{
    /**
     * CREATE ADJUSTMENT (DRAFT)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'adjustment_date' => 'required|date',
            'warehouse_id'    => 'required|exists:warehouses,id,is_active,1',
            'reason'          => 'nullable|string',
            'notes'           => 'nullable|string',
            'items'           => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.actual_qty' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($validated) {

            $adjustment = StockAdjustment::create([
                'adjustment_number' => 'ADJ-' . now()->format('YmdHis'),
                'adjustment_date'   => $validated['adjustment_date'],
                'warehouse_id'      => $validated['warehouse_id'],
                'reason'            => $validated['reason'] ?? null,
                'notes'             => $validated['notes'] ?? null,
                'status'            => 'draft',
                'created_by'        => Auth::id(),
            ]);

            foreach ($validated['items'] as $item) {

                $stock = Stock::where('product_id', $item['product_id'])
                    ->where('warehouse_id', $validated['warehouse_id'])
                    ->first();

                $systemQty = $stock?->qty ?? 0;
                $difference = $item['actual_qty'] - $systemQty;

                StockAdjustmentItem::create([
                    'stock_adjustment_id' => $adjustment->id,
                    'product_id'          => $item['product_id'],
                    'system_qty'          => $systemQty,
                    'actual_qty'          => $item['actual_qty'],
                    'difference'          => $difference,
                ]);
            }
        });

        return response()->json([
            'message' => 'Stock adjustment berhasil dibuat (draft)'
        ], 201);
    }

    /**
     * POST ADJUSTMENT (FINAL)
     */
    public function post($id)
    {
        $adjustment = StockAdjustment::with('items')
            ->lockForUpdate()
            ->findOrFail($id);

        if ($adjustment->status === 'posted') {
            throw ValidationException::withMessages([
                'status' => 'Stock adjustment sudah diposting sebelumnya'
            ]);
        }

        DB::transaction(function () use ($adjustment) {

            foreach ($adjustment->items as $item) {

                if ($item->difference == 0) {
                    continue;
                }

                // Insert stock movement
                StockMovement::create([
                    'product_id'    => $item->product_id,
                    'warehouse_id'  => $adjustment->warehouse_id,
                    'movement_type' => 'ADJUSTMENT',
                    'quantity'      => $item->difference,
                    'reference_type'=> StockAdjustment::class,
                    'reference_id'  => $adjustment->id,
                    'created_by'    => Auth::id(),
                ]);

                // Update stock
                $stock = Stock::firstOrCreate(
                    [
                        'product_id'   => $item->product_id,
                        'warehouse_id' => $adjustment->warehouse_id,
                    ],
                    ['qty' => 0]
                );

                $stock->increment('quantity', $item->difference);
            }

            $adjustment->update(['status' => 'posted']);
        });

        return response()->json([
            'message' => 'Stock adjustment berhasil diposting dan stok telah diperbarui'
        ]);
    }
        
        public function index()
        {
            $adjustments = StockAdjustment::with([
                'warehouse',
                'creator',
                'items.product'
            ])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

            return response()->json($adjustments);
        }
        
        public function show($id)
        {
            $adjustment = StockAdjustment::withTrashed([
                'warehouse',
                'creator',
                'items.product'
            ])->findOrFail($id);

            return response()->json($adjustment);
        }

        public function update(Request $request, $id)
        {
            $adjustment = StockAdjustment::findOrFail($id);

            if ($adjustment->status === 'posted') {
                return response()->json([
                    'message' => 'Stock adjustment yang sudah dipost tidak bisa diubah'
                ], 422);
            }

            $validated = $request->validate([
                'adjustment_date' => 'sometimes|date',
                'warehouse_id'    => 'sometimes|exists:warehouses,id,is_active,1',
                'reason'          => 'nullable|string',
                'notes'           => 'nullable|string',
            ]);

            // 3. Eksekusi Update
                    $adjustment->update($validated);

            // 4. Return Response JSON (Agar di Postman muncul keterangannya)
                    return response()->json([
                        'message' => 'Stock adjustment berhasil diperbarui',
                        'data' => $adjustment->load('warehouse') // Muat relasi jika perlu
                    ]);
        }

        public function destroy($id)
        {
            $adjustment = StockAdjustment::findOrFail($id);

            if ($adjustment->status === 'posted') {
                return response()->json([
                    'message' => 'Data yang sudah dipost tidak bisa dihapus'
                ], 422);
            }
            
                $adjustment->delete();

                // Mengembalikan response JSON yang jelas
                return response()->json([
                'message' => 'Data berhasil dihapus',
                'id' => $id
                ], 200);
        }

                /**
                * Restore data yang dihapus.
                */
                public function restore($id)
                    {
                        $adjustment = StockAdjustment::onlyTrashed()->findOrFail($id);
                        $adjustment->restore();

                        return response()->json(['message' => 'Stock adjustment berhasil dikembalikan dari sampah']);
                    }
}