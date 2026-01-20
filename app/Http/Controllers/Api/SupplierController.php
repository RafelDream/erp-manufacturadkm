<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;


class SupplierController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => Supplier::latest()->get()
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama' => 'required|string',
            'email' => 'required|email',
            'alamat' => 'required|string',
            'telepon' => 'nullable|string',
            'kontak_person' => 'nullable|string',
            'is_active' => 'nullable|boolean'
        ]);

        $supplier = Supplier::create($data);

        return response()->json([
            'message' => 'Supplier berhasil ditambahkan',
            'data' => $supplier
        ], 201);
    }

    public function show($id)
    {
        $supplier = Supplier::findOrFail($id);

        return response()->json([
            'data' => $supplier
        ]);
    }

    public function update(Request $request, $id)
    {
        $supplier = Supplier::findOrFail($id);

        $data = $request->validate([
            'nama' => 'nullable|string',
            'email' => 'nullable|email',
            'alamat' => 'nullable|string',
            'telepon' => 'nullable|string',
            'kontak_person' => 'nullable|string',
            'is_active' => 'nullable|boolean'
        ]);

        $supplier->update($data);

        return response()->json([
            'message' => 'Supplier berhasil diperbarui',
            'data' => $supplier
        ]);
    }

    public function destroy($id)
    {
        $supplier = Supplier::findOrFail($id);
        $supplier->delete();

        return response()->json([
            'message' => 'Supplier berhasil dihapus'
        ]);
    }

    public function restore($id)
    {
        $supplier = Supplier::withTrashed()->findOrFail($id);
        $supplier->restore();

        return response()->json([
            'message' => 'Supplier berhasil direstore',
            'data' => $supplier
        ]);
    }
}