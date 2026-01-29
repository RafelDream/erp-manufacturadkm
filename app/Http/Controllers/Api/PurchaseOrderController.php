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
    /**
     * GET - list PO
     */
    public function index()
    {
        return response()->json(
            PurchaseOrder::with([
                'items.rawMaterial',
                'items.product',
                'supplier',
                'purchaseRequest',
                'creator'
            ])
            ->latest()
            ->get()
        );
    }

    /**
     * GET - detail PO
     */
    public function show($id)
    {
        return response()->json(
            PurchaseOrder::with([
                'items.rawMaterial',
                'items.product',
                'supplier',
                'purchaseRequest',
                'creator'
            ])
            ->findOrFail($id)
        );
    }

    /**
     * POST - Generate PO from APPROVED PR
     */
    public function generateFromPR($prId)
    {
        $pr = PurchaseRequest::with('items')->findOrFail($prId);

        if ($pr->status !== 'approved') {
            return response()->json([
                'message' => 'Hanya PR dengan status APPROVED yang bisa dibuat PO'
            ], 422);
        }

        // Cegah PR dibuat PO dua kali
        if (PurchaseOrder::where('purchase_request_id', $pr->id)->exists()) {
            return response()->json([
                'message' => 'PO untuk PR ini sudah pernah dibuat'
            ], 422);
        }

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

            return $po;
        });

        return response()->json([
            'message' => 'Purchase Order berhasil dibuat dari PR',
            'data' => $po->load('items')
        ], 201);
    }

    /**
     * PUT - Update PO (selama masih DRAFT)
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
            'supplier_id' => 'nullable|exists:suppliers,id',
            'order_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $po->update($validated);

        return response()->json([
            'message' => 'Purchase Order berhasil diupdate'
        ]);
    }

    /**
     * POST - Set price item PO
     */
    public function updateItemPrice(Request $request, $itemId)
    {
        $item = PurchaseOrderItem::findOrFail($itemId);

        $validated = $request->validate([
            'price' => 'required|numeric|min:0',
        ]);

        $item->update([
            'price' => $validated['price'],
            'subtotal' => $item->quantity * $validated['price']
        ]);

        return response()->json([
            'message' => 'Harga item PO berhasil diupdate'
        ]);
    }

    /**
     * POST - Submit PO ke supplier
     */
    public function submit($id)
    {
        $po = PurchaseOrder::findOrFail($id);

        if ($po->status !== 'draft') {
            return response()->json([
                'message' => 'Hanya PO DRAFT yang bisa dikirim'
            ], 422);
        }

        $po->update(['status' => 'sent']);

        return response()->json([
            'message' => 'Purchase Order berhasil dikirim ke supplier'
        ]);
    }

    public function approve($id)
    {
        $po = PurchaseOrder::findOrFail($id);

        if ($po->status !== 'sent') {
            return response()->json([
                'message' => 'Hanya Purchase Order yang sudah dikirim yang bisa disetujui'
            ], 422);
        }

        $po->update(['status' => 'received']);

        return response()->json([
            'message' => 'Purchase Order berhasil diterima'
        ]);
    }
    /**
     * DELETE - Soft delete PO
     */
    public function destroy($id)
    {
        PurchaseOrder::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Purchase Order berhasil dihapus'
        ]);
    }

    /**
     * RESTORE - restore PO
     */
    public function restore($id)
    {
        PurchaseOrder::withTrashed()
            ->findOrFail($id)
            ->restore();

        return response()->json([
            'message' => 'Purchase Order berhasil dikembalikan'
        ]);
    }
}
