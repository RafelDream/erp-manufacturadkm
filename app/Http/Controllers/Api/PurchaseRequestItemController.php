<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequestItem;
use App\Models\PurchaseRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseRequestItemController extends Controller
{
    /**
     * Add items to PR (only DRAFT)
     */
    Public function index($purchaseRequestId)
    {
        $items = PurchaseRequestItem::with(['rawMaterial', 'product', 'unit'])
            ->where('purchase_request_id', $purchaseRequestId)
            ->get();

        return response()->json($items);
    } 
    public function store(Request $request)
    {
        $validated = $request->validate([
            'purchase_request_id' => 'required|exists:purchase_requests,id',
            'items' => 'required|array|min:1',
            'items.*.raw_material_id' => 'nullable|exists:raw_materials,id',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.unit_id' => 'required|exists:units,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.reference_no' => 'nullable|string',
            'items.*.notes' => 'nullable|string',
        ]);

        $pr = PurchaseRequest::findOrFail($validated['purchase_request_id']);

        if ($pr->status !== 'draft') {
            return response()->json([
                'message' => 'Hanya PR DRAFT yang bisa ditambahkan item'
            ], 422);
        }

        try {
            DB::transaction(function () use ($validated, $pr) {
                foreach ($validated['items'] as $item) {
                    $rawMaterialId = $item['raw_material_id'] ?? null;
                    $productId = $item['product_id'] ?? null;

                    // ✅ VALIDASI 1: Harus pilih salah satu
                    if (!$rawMaterialId && !$productId) {
                        throw new \Exception('Item harus memilih raw material atau product');
                    }

                    // ✅ VALIDASI 2: Tidak boleh keduanya terisi
                    if ($rawMaterialId && $productId) {
                        throw new \Exception('Item tidak boleh memilih raw material dan product sekaligus');
                    }

                    // ✅ VALIDASI 3: Type harus match
                    if ($pr->type === 'raw_materials' && !$rawMaterialId) {
                        throw new \Exception('PR type "raw_materials" harus menggunakan raw_material_id');
                    }

                    if ($pr->type === 'product' && !$productId) {
                        throw new \Exception('PR type "product" harus menggunakan product_id');
                    }

                    PurchaseRequestItem::create([
                        'purchase_request_id' => $validated['purchase_request_id'],
                        'raw_material_id' => $rawMaterialId,
                        'product_id' => $productId,
                        'unit_id' => $item['unit_id'],
                        'quantity' => $item['quantity'],
                        'reference_no' => $item['reference_no'] ?? null,
                        'notes' => $item['notes'] ?? null,
                    ]);
                }
            });

            return response()->json([
                'message' => 'Items berhasil ditambahkan ke PR'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menambahkan items',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete item from PR (only DRAFT)
     */
    public function destroy($id)
    {
        $item = PurchaseRequestItem::with('purchaseRequest')->findOrFail($id);

        if ($item->purchaseRequest->status !== 'draft') {
            return response()->json([
                'message' => 'Hanya item PR DRAFT yang bisa dihapus'
            ], 422);
        }

        $item->delete();

        return response()->json([
            'message' => 'Item berhasil dihapus'
        ]);
    }

    /**
     * Update item quantity/notes (only DRAFT)
     */
    public function update(Request $request, $id)
    {
        $item = PurchaseRequestItem::with('purchaseRequest')->findOrFail($id);

        if ($item->purchaseRequest->status !== 'draft') {
            return response()->json([
                'message' => 'Hanya item PR DRAFT yang bisa diupdate'
            ], 422);
        }

        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0.001',
            'reference_no' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $item->update($validated);

        return response()->json([
            'message' => 'Item berhasil diupdate'
        ]);
    }
}
