<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'permission:manage-units']);
    }

    public function index(Request $request)
    {
        $query = Unit::query();

        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        return response()->json(
            $query->latest()->paginate(10)
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kode' => 'required|string|max:20|unique:units,kode',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
        ]);

        $unit = Unit::create($validated);

        return response()->json([
            'message' => 'Unit berhasil ditambahkan',
            'data' => $unit
        ], 201);
    }

    public function show(Unit $unit)
    {
        return response()->json($unit);
    }

    public function update(Request $request, Unit $unit)
    {
        $validated = $request->validate([
            'kode' => 'required|string|max:20|unique:units,kode,' . $unit->id,
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $unit->update($validated);

        return response()->json([
            'message' => 'Unit berhasil diperbarui',
            'data' => $unit
        ]);
    }

    public function destroy(Unit $unit)
    {
        $unit->delete();

        return response()->json([
            'message' => 'Unit berhasil dihapus'
        ]);
    }

    public function restore($id)
    {
        $unit = Unit::withTrashed()->findOrFail($id);
        $unit->restore();

        return response()->json([
            'message' => 'Unit berhasil dipulihkan',
            'data' => $unit
        ]);
    }
}
