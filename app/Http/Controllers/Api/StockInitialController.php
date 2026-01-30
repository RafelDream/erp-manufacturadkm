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
    /**
     * Display a listing of the stocks.
     */
    public function index()
    {
        $stocks = Stock::with(['product', 'warehouse'])->get();

        $data = $stocks->map(function ($stock) {
            return [
                'stock_id'     => $stock->id,
                'product_id'    => $stock->product_id,
                'product_name'  => $stock->product->name ?? 'N/A',
                'warehouse'     => $stock->warehouse->name ?? 'N/A',
                'quantity'      => $stock->quantity,
            ];
        });

        return response()->json($data);
    }

    /**
     * Display the specified stock.
     */
    public function show($id)
    {
        return response()->json(
            Stock::with(['product', 'warehouse'])->findOrFail($id)
        );
    }

    /**
     * Store initial stock and record movements.
     */
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
                // Update or Create the stock record
                $stock = Stock::firstOrCreate(
                    [
                        'product_id' => $item['product_id'],
                        'warehouse_id' => $validated['warehouse_id'],
                    ],
                    [
                        'quantity' => 0
                    ]
                );

                // Increment the physical stock
                $stock->increment('quantity', $item['quantity']);

                // Record the movement history
                StockMovement::create([
                    'product_id'     => $item['product_id'],
                    'warehouse_id'   => $validated['warehouse_id'],
                    'type'           => 'in',
                    'quantity'       => $item['quantity'],
                    'reference_type' => 'Stok Awal',
                    'reference_id'   => $stock->id,
                    'notes'          => 'Stok awal',
                    'created_by'     => Auth::id(),
                ]);
            }
        });

        return response()->json([
            'message' => 'Stok awal berhasil diinput'
        ], 201);
    }
}