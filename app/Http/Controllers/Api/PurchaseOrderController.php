<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public function index()
    {
        return response()->json(
            PurchaseOrder::with([
                'items.rawMaterial',
                'items.product',
                'items.unit',
                'supplier',
                'purchaseRequest.items',
                'creator'
            ])->latest()->get()
        );
    }

    public function show($id)
    {
        return response()->json(
            PurchaseOrder::with([
                'items.rawMaterial',
                'items.product',
                'items.unit',
                'supplier',
                'purchaseRequest.items',
                'creator'
            ])->findOrFail($id)
        );
    }

    /**
     * Generate PO from APPROVED PR
     */
    public function generateFromPR($prId)
    {
        $pr = PurchaseRequest::with('items')->findOrFail($prId);

        // ✅ CEK STATUS APPROVED
        if ($pr->status !== 'approved') {
            return response()->json([
                'message' => 'Hanya PR yang APPROVED yang bisa dibuat PO'
            ], 422);
        }

        // ✅ CEK APAKAH SUDAH PERNAH DIBUAT PO
        if ($pr->completed_at || PurchaseOrder::where('purchase_request_id', $pr->id)->exists()) {
            return response()->json([
                'message' => 'PO dari PR ini sudah pernah dibuat'
            ], 422);
        }

        try {
            $po = DB::transaction(function () use ($pr) {
                $po = PurchaseOrder::create([
                    'kode' => 'PO-' . now()->format('YmdHis'),
                    'purchase_request_id' => $pr->id,
                    'order_date' => now(),
                    'status' => 'draft',
                    'created_by' => Auth::id(),
                ]);

                foreach ($pr->items as $item) {
                    PurchaseOrderItem::create([
                        'purchase_order_id' => $po->id,
                        'raw_material_id' => $item->raw_material_id,
                        'product_id' => $item->product_id,
                        'unit_id' => $item->unit_id,
                        'quantity' => $item->quantity,
                    ]);
                }

                // ✅ UPDATE STATUS PR MENJADI COMPLETED
                $pr->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'completed_by' => Auth::id(),
                ]);

                return $po;
            });

            return response()->json([
                'message' => 'PO berhasil dibuat dari PR',
                'data' => $po->load('items.rawMaterial', 'items.product', 'items.unit')
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal membuat PO',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update PO (supplier, tanggal, notes)
     */
    public function update(Request $request, $id)
    {
        $po = PurchaseOrder::findOrFail($id);

        if ($po->status !== 'draft') {
            return response()->json([
                'message' => 'Hanya PO DRAFT yang bisa diupdate'
            ], 422);
        }

        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'order_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $po->update($validated);

        return response()->json([
            'message' => 'PO berhasil diupdate'
        ]);
    }

    /**
     * Update harga item PO
     */
    public function updateItemPrice(Request $request, $itemId)
    {
        $item = PurchaseOrderItem::findOrFail($itemId);

        // ✅ CEK APAKAH PO MASIH DRAFT
        if ($item->purchaseOrder->status !== 'draft') {
            return response()->json([
                'message' => 'Hanya item PO DRAFT yang bisa diupdate'
            ], 422);
        }

        $validated = $request->validate([
            'price' => 'required|numeric|min:0',
        ]);

        $item->update([
            'price' => $validated['price'],
            'subtotal' => $item->quantity * $validated['price']
        ]);

        return response()->json([
            'message' => 'Harga item berhasil diupdate'
        ]);
    }

    /**
     * Submit PO (kirim ke supplier)
     */
    public function submit($id)
    {
        $po = PurchaseOrder::with('items')->findOrFail($id);

        if ($po->status !== 'draft') {
            return response()->json([
                'message' => 'Hanya PO DRAFT yang bisa dikirim'
            ], 422);
        }

        // ✅ VALIDASI SUPPLIER WAJIB
        if (!$po->supplier_id) {
            return response()->json([
                'message' => 'Supplier wajib diisi sebelum submit'
            ], 422);
        }

        // ✅ VALIDASI SEMUA ITEM HARUS PUNYA HARGA
        $itemsWithoutPrice = $po->items()->whereNull('price')->count();
        if ($itemsWithoutPrice > 0) {
            return response()->json([
                'message' => 'Semua item harus memiliki harga sebelum submit'
            ], 422);
        }

        $po->update(['status' => 'sent']);

        return response()->json([
            'message' => 'PO berhasil dikirim ke supplier'
        ]);
    }

    /**
     * Terima barang (Goods Receipt)
     */
    public function receive($id)
    {
        $po = PurchaseOrder::with('items')->findOrFail($id);

        if ($po->status !== 'sent') {
            return response()->json([
                'message' => 'PO belum dikirim atau sudah diterima'
            ], 422);
        }

        $po->update(['status' => 'received']);

        return response()->json([
            'message' => 'Barang berhasil diterima'
        ]);
    }

    /**
     * DELETE - soft delete
     */
    public function destroy($id)
    {
        $po = PurchaseOrder::with('purchaseRequest')->findOrFail($id);

        if (in_array($po->status, ['sent', 'received', 'closed'])) {
            return response()->json([
                'message' => 'PO yang sudah dikirim/diterima tidak dapat dihapus'
            ], 422);
        }

        try {
            DB::transaction(function () use ($po) {
                if ($po->purchaseRequest) {
                    $po->purchaseRequest->update([
                        'status' => 'approved',
                        'completed_at' => null,
                        'completed_by' => null,
                    ]);
                }

                $po->delete();
            });

            return response()->json([
                'message' => 'PO berhasil dihapus dan PR dikembalikan ke status approved'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menghapus PO',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * RESTORE
     */
    public function restore($id)
    {
        PurchaseOrder::withTrashed()->findOrFail($id)->restore();

        return response()->json([
            'message' => 'PO berhasil direstore'
        ]);
    }
}