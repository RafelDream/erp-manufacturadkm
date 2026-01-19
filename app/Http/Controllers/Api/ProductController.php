<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;

class ProductController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'permission:manage-products']);
    }

    public function index()
    {
        return response()->json(
            Product::with('unit')->latest()->paginate(10)
        );
    }
    
    public function store(Request $request)
    {
        $validated = $request->validate([
            'kode' => 'required|unique:products,kode',
            'name' => 'required|string',
            'unit_id' => 'required|exists:units,id',
            'tipe' => 'required|string',
            'volume' => 'required|numeric',
            'harga' => 'required|numeric|min:0',
            'is_returnable' => 'boolean',
        ]);

        $product = Product::create($validated);

        return response()->json([
            'message' => 'Produk berhasil ditambahkan',
            'data' => $product->load('unit')
        ], 201);
    }
    
    public function show(Product $product)
    {
        return response()->json(
            $product->load('unit')
        );
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'kode' => 'nullable|unique:products,kode,' . $product->id,
            'name' => 'nullable|string',
            'unit_id' => 'nullable|exists:units,id',
            'tipe' => 'nullable|string',
            'volume' => 'nullable|numeric',
            'harga' => 'nullable|numeric|min:0',
            'is_returnable' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $product->update($validated);

        return response()->json([
            'message' => 'Produk berhasil diperbarui',
            'data' => $product->load('unit')
        ]);
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json([
            'message' => 'Produk berhasil dihapus'
        ]);
    }

    public function restore($id)
    {
        $product = Product::withTrashed()->findOrFail($id);
        $product->restore();

        return response()->json([
            'message' => 'Produk berhasil dipulihkan',
            'data' => $product->load('unit')
        ]);
    }
}
