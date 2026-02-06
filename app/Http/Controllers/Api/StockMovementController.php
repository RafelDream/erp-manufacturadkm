<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use App\Models\RawMaterialStockMovement;
use App\Models\Product;
use App\Models\RawMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class StockMovementController extends Controller
{
    /**
     * Menampilkan semua histori stok (Gabungan Product & Raw Material)
     */
    public function index(Request $request)
    {
        // Query untuk Product Movements
        $productMovements = DB::table('stock_movements')
            ->join('products', 'stock_movements.product_id', '=', 'products.id')
            ->select(
                'stock_movements.id',
                'products.name as item_name',
                DB::raw("'Product' as item_type"),
                'stock_movements.type as movement_type',
                'stock_movements.quantity',
                'stock_movements.created_at'
            );

        // Query untuk Raw Material Movements
        $movements = DB::table('raw_material_stock_movements')
            ->join('raw_materials', 'raw_material_stock_movements.raw_material_id', '=', 'raw_materials.id')
            ->select(
                'raw_material_stock_movements.id',
                'raw_materials.name as item_name',
                DB::raw("'RawMaterial' as item_type"),
                'raw_material_stock_movements.movement_type',
                'raw_material_stock_movements.quantity', // disesuaikan kolomnya
                'raw_material_stock_movements.created_at'
            )
            ->union($productMovements)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $movements
        ]);
    }

    /**
     * Simpan Mutasi Stok Baru (Universal)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category'      => 'required|in:product,raw_material',
            'item_id'       => 'required',
            'warehouse_id'  => 'required|exists:warehouses,id',
            'type'          => 'required', // IN, OUT, ADJUSTMENT
            'quantity'      => 'required|numeric',
            'notes'         => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        try {
            DB::beginTransaction();

            if ($request->category === 'product') {
                $movement = StockMovement::create([
                    'product_id'   => $request->item_id,
                    'warehouse_id' => $request->warehouse_id,
                    'type'         => strtolower($request->type), // product pakai 'in'/'out'
                    'quantity'     => $request->quantity,
                    'notes'        => $request->notes,
                    'created_by'   => Auth::id() ?? 1, // Default ke 1 jika testing Postman
                ]);
            } else {
                $movement = RawMaterialStockMovement::create([
                    'raw_material_id' => $request->item_id,
                    'warehouse_id'    => $request->warehouse_id,
                    'movement_type'   => strtoupper($request->type), // raw pakai 'IN'/'OUT'
                    'quantity'        => $request->quantity,
                    'created_by'      => Auth::id() ?? 1,
                ]);
            }

            DB::commit();
            return response()->json(['success' => true, 'data' => $movement], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Menampilkan Saldo Stok Saat Ini (Current Stock) 
     * Terintegrasi untuk Product dan Raw Material
     */


    public function getStockSummary(Request $request)
    {
        // 1. Ambil Stok Barang Jadi (Product)
        // Diasumsikan 'in' adalah penambah, 'out' adalah pengurang
        $productStock = Product::with(['unit'])->get()->map(function ($product) {
        $in = StockMovement::where('product_id', $product->id)->where('type', 'in')->sum('quantity');
        $out = StockMovement::where('product_id', $product->id)->where('type', 'out')->sum('quantity');
        $adj = StockMovement::where('product_id', $product->id)->where('type', 'adjustment')->sum('quantity');
        
        return [
            'id' => $product->id,
            'name' => $product->name,
            'code' => $product->kode,
            'type' => 'Product',
            'unit' => $product->unit->name ?? '-',
            'current_stock' => ($in + $adj) - $out,
            'is_active' => $product->is_active
        ];
    });

        // 2. Ambil Stok Bahan Baku (Raw Material)
        $rawMaterialStock = RawMaterial::get()->map(function ($raw) {
        $in = RawMaterialStockMovement::where('raw_material_id', $raw->id)
            ->whereIn('movement_type', ['IN', 'TRANSFER_IN', 'ADJUSTMENT'])
            ->sum('quantity');
        $out = RawMaterialStockMovement::where('raw_material_id', $raw->id)
            ->whereIn('movement_type', ['OUT', 'TRANSFER_OUT'])
            ->sum('quantity');

        return [
            'id' => $raw->id,
            'name' => $raw->name,
            'code' => $raw->code,
            'type' => 'RawMaterial',
            'unit' => $raw->unit, // Sesuai migration Anda (string)
            'current_stock' => $in - $out,
            'is_active' => $raw->is_active
        ];
    });

        // Gabungkan hasil
        return response()->json([
        'success' => true,
        'data' => $productStock->concat($rawMaterialStock)
        ]);
    }
}
