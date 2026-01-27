<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RawMaterial;

class RawMaterialController extends Controller
{
     public function __construct()
    {
        $this->middleware(['auth:sanctum', 'permission:manage-raw-materials']);
    }

    /**
     * GET /raw-materials
     */
    public function index()
    {
        return response()->json(
            RawMaterial::latest()->paginate(10)
        );
    }

    /**
     * POST /raw-materials
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'     => 'required|string|unique:raw_materials,code',
            'name'     => 'required|string',
            'category' => 'required|string',
            'unit'     => 'required|string|max:20',
        ]);

        $rawMaterial = RawMaterial::create($validated);

        return response()->json([
            'message' => 'Raw material berhasil ditambahkan',
            'data'    => $rawMaterial
        ], 201);
    }

    /**
     * GET /raw-materials/{id}
     */
    public function show(RawMaterial $rawMaterial)
    {
        return response()->json($rawMaterial);
    }

    /**
     * PUT /raw-materials/{id}
     */
    public function update(Request $request, RawMaterial $rawMaterial)
    {
        $validated = $request->validate([
            'code'     => 'nullable|string|unique:raw_materials,code,' . $rawMaterial->id,
            'name'     => 'nullable|string',
            'category' => 'nullable|string',
            'unit'     => 'nullable|string|max:20',
            'is_active'=> 'boolean',
        ]);

        $rawMaterial->update($validated);

        return response()->json([
            'message' => 'Raw material berhasil diperbarui',
            'data'    => $rawMaterial
        ]);
    }

    /**
     * DELETE /raw-materials/{id}
     */
    public function destroy(RawMaterial $rawMaterial)
    {
        $rawMaterial->delete();

        return response()->json([
            'message' => 'Raw material berhasil dihapus'
        ]);
    }

    /**
     * POST /raw-materials/{id}/restore
     */
    public function restore($id)
    {
        $rawMaterial = RawMaterial::withTrashed()->findOrFail($id);
        $rawMaterial->restore();

        return response()->json([
            'message' => 'Raw material berhasil dipulihkan',
            'data'    => $rawMaterial
        ]);
    }
}
