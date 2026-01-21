<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class StockInitialController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($validated) {

            foreach ($validated['items'] as $item) {

                $stock = Stock::firstOrCreate(
                    [
                        'product_id' => $item['product_id'],
                        'warehouse_id' => $validated['warehouse_id'],
                    ],
                    [
                        'quantity' => 0
                    ]
                );

                $stock->increment('quantity', $item['quantity']);

                StockMovement::create([
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $validated['warehouse_id'],
                    'type' => 'in',
                    'quantity' => $item['quantity'],
                    'reference_type' => 'initial_stock',
                    'reference_id' => null,
                    'notes' => 'Stok awal',
                    'created_by' => Auth::id(),
                ]);
            }
        });

        return response()->json([
            'message' => 'Stok awal berhasil diinput'
        ]);
    }
}
