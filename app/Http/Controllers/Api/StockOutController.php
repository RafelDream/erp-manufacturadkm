<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockOut;
use App\Models\StockRequest;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class StockOutController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'stock_request_id' => 'required|exists:stock_requests,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'out_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $stockRequest = StockRequest::with('items')->findOrFail($validated['stock_request_id']);

        if ($stockRequest->status !== 'approved') {
            return response()->json([
                'message' => 'Stock request belum disetujui'
            ], 422);
        }

        DB::transaction(function () use ($validated, $stockRequest) {

            $stockOut = StockOut::create([
                'stock_request_id' => $stockRequest->id,
                'warehouse_id' => $validated['warehouse_id'],
                'out_date' => $validated['out_date'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            foreach ($stockRequest->items as $item) {

                $stock = Stock::where('product_id', $item->product_id)
                    ->where('warehouse_id', $validated['warehouse_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$stock || $stock->quantity < $item->quantity) {
                    throw new \Exception('Stock tidak mencukupi untuk produk ID ' . $item->product_id);
                }

                $stockOut->items()->create([
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                ]);

                $stock->decrement('quantity', $item->quantity);

                StockMovement::create([
                    'product_id' => $item->product_id,
                    'warehouse_id' => $validated['warehouse_id'],
                    'type' => 'out',
                    'quantity' => $item->quantity,
                    'reference_type' => 'stock_out',
                    'reference_id' => $stockOut->id,
                    'notes' => 'Pengeluaran barang dari stock request #' . $stockRequest->id,
                    'created_by' => Auth::id(),
                ]);
            }
        });

        return response()->json([
            'message' => 'Stock out & stock movement berhasil dibuat'
        ]);
    }
}
