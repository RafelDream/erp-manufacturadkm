<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => Warehouse::latest()->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kode' => 'required|string|unique:warehouses,kode',
            'name' => 'required|string',
            'lokasi' => 'nullable|string',
            'deskripsi' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $warehouse = Warehouse::create($validated);

        return response()->json([
            'message' => 'Warehouse berhasil dibuat',
            'data' => $warehouse
        ], 201);
    }


    public function show(Warehouse $warehouse)
    {
        return response()->json([
            'data' => $warehouse
        ]);
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $validated = $request->validate([
            'kode' => 'required|string|unique:warehouses,kode,' . $warehouse->id,
            'name' => 'required|string',
            'lokasi' => 'nullable|string',
            'deskripsi' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $warehouse->update($validated);

        return response()->json([
            'message' => 'Warehouse berhasil diperbarui',
            'data' => $warehouse
        ]);
    }

    public function destroy(Warehouse $warehouse)
    {
        $warehouse->delete();

        return response()->json([
            'message' => 'Warehouse berhasil dihapus'
        ]);
    }

    public function restore($id)
    {
        $warehouse = Warehouse::withTrashed()->findOrFail($id);
        $warehouse->restore();

        return response()->json([
            'message' => 'Warehouse berhasil dipulihkan',
            'data' => $warehouse
        ]);
    }
}
