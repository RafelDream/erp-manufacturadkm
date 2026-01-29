<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use Illuminate\Http\Request;

class PurchaseRequestItemController extends Controller
{
    /**
     * POST - tambah item ke PR
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'purchase_request_id' => 'required|exists:purchase_requests,id',
            'raw_material_id'     => 'nullable|exists:raw_materials,id',
            'product_id'          => 'nullable|exists:products,id',
            'unit_id'             => 'required|exists:units,id',
            'quantity'            => 'required|numeric|min:0.001',
            'reference_no'        => 'nullable|string',
            'notes'               => 'nullable|string',
        ]);

        // pastikan hanya salah satu yg diisi
        if (
            empty($validated['raw_material_id']) &&
            empty($validated['product_id'])
        ) {
            return response()->json([
                'message' => 'Raw material atau product wajib diisi'
            ], 422);
        }

        if (
            !empty($validated['raw_material_id']) &&
            !empty($validated['product_id'])
        ) {
            return response()->json([
                'message' => 'Pilih salah satu: raw material ATAU product'
            ], 422);
        }

        $pr = PurchaseRequest::findOrFail($validated['purchase_request_id']);

        if ($pr->status !== 'draft') {
            return response()->json([
                'message' => 'Item hanya bisa ditambahkan saat PR Draft'
            ], 422);
        }

        $item = PurchaseRequestItem::create($validated);

        return response()->json([
            'message' => 'Item PR berhasil ditambahkan',
            'data' => $item
        ], 201);
    }

    /**
     * PUT - update item PR
     */
    public function update(Request $request, $id)
    {
        $item = PurchaseRequestItem::findOrFail($id);

        if ($item->purchaseRequest->status !== 'draft') {
            return response()->json([
                'message' => 'Item tidak bisa diubah karena PR sudah diproses'
            ], 422);
        }

        $validated = $request->validate([
            'unit_id'      => 'required|exists:units,id',
            'quantity'     => 'required|numeric|min:0.001',
            'reference_no' => 'nullable|string',
            'notes'        => 'nullable|string',
        ]);

        $item->update($validated);

        return response()->json([
            'message' => 'Item PR berhasil diupdate'
        ]);
    }

    /**
     * DELETE - hapus item PR
     */
    public function destroy($id)
    {
        $item = PurchaseRequestItem::findOrFail($id);

        if ($item->purchaseRequest->status !== 'draft') {
            return response()->json([
                'message' => 'Item tidak bisa dihapus karena PR sudah diproses'
            ], 422);
        }

        $item->delete();

        return response()->json([
            'message' => 'Item PR berhasil dihapus'
        ]);
    }
}
