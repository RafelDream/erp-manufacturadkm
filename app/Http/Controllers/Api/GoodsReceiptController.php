<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\RawMaterialStock;
use App\Models\RawMaterialStockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GoodsReceiptController extends Controller
{
    /**
     * GET - List LPB
     */
    public function index(Request $request)
    {
        $query = GoodsReceipt::with([
            'items.rawMaterial',
            'items.product',
            'items.unit',
            'purchaseOrder.supplier',
            'warehouse',
            'creator'
        ]);

        // Filter by date range
        if ($request->start_date && $request->end_date) {
            $query->whereBetween('receipt_date', [$request->start_date, $request->end_date]);
        }

        // Filter by status
        if ($request->status) {
            $query->where('status', $request->status);
        }

        // Filter by PO
        if ($request->purchase_order_id) {
            $query->where('purchase_order_id', $request->purchase_order_id);
        }

        return response()->json($query->latest()->get());
    }

    /**
     * GET - Detail LPB
     */
    public function show($id)
    {
        return response()->json(
            GoodsReceipt::with([
                'items.rawMaterial',
                'items.product',
                'items.unit',
                'purchaseOrder.supplier',
                'purchaseOrder.items',
                'warehouse',
                'creator',
                'poster'
            ])->findOrFail($id)
        );
    }

    /**
     * POST - Create LPB from PO
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'receipt_date' => 'required|date',
            'delivery_note_number' => 'nullable|string',
            'vehicle_number' => 'nullable|string',
            'type' => 'required|in:GOODS_RECEIPT,RETURN',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.purchase_order_item_id' => 'required|exists:purchase_order_items,id',
            'items.*.quantity_received' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string',
        ]);

        $po = PurchaseOrder::with('items')->findOrFail($validated['purchase_order_id']);

        // Validasi PO harus sudah sent
        if ($po->status !== 'sent') {
            return response()->json([
                'message' => 'PO belum dikirim ke supplier'
            ], 422);
        }

        try {
            $gr = DB::transaction(function () use ($validated, $po) {
                // Create Goods Receipt
                $gr = GoodsReceipt::create([
                    'receipt_number' => 'GR-' . now()->format('YmdHis'),
                    'receipt_date' => $validated['receipt_date'],
                    'purchase_order_id' => $po->id,
                    'warehouse_id' => $validated['warehouse_id'],
                    'delivery_note_number' => $validated['delivery_note_number'] ?? null,
                    'vehicle_number' => $validated['vehicle_number'] ?? null,
                    'po_reference' => $po->kode,
                    'type' => $validated['type'],
                    'status' => 'draft',
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => Auth::id(),
                ]);

                // Create GR Items
                foreach ($validated['items'] as $itemData) {
                    $poItem = $po->items()->findOrFail($itemData['purchase_order_item_id']);

                    $gr->items()->create([
                        'raw_material_id' => $poItem->raw_material_id,
                        'product_id' => $poItem->product_id,
                        'unit_id' => $poItem->unit_id,
                        'quantity_ordered' => $poItem->quantity,
                        'quantity_received' => $itemData['quantity_received'],
                        'quantity_remaining' => $poItem->quantity - $itemData['quantity_received'],
                        'quantity_actual' => $itemData['quantity_received'], // Default sama dengan received
                        'notes' => $itemData['notes'] ?? null,
                    ]);
                }

                return $gr;
            });

            return response()->json([
                'message' => 'Goods Receipt berhasil dibuat',
                'data' => $gr->load('items.rawMaterial', 'items.product')
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal membuat Goods Receipt',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT - Update LPB (only draft)
     */
    public function update(Request $request, $id)
    {
        $gr = GoodsReceipt::findOrFail($id);

        if ($gr->status !== 'draft') {
            return response()->json([
                'message' => 'Hanya LPB draft yang bisa diupdate'
            ], 422);
        }

        $validated = $request->validate([
            'receipt_date' => 'required|date',
            'warehouse_id' => 'required|exists:warehouses,id',
            'delivery_note_number' => 'nullable|string',
            'vehicle_number' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $gr->update($validated);

        return response()->json([
            'message' => 'Goods Receipt berhasil diupdate'
        ]);
    }

    /**
     * POST - Post LPB (update stock)
     */
    public function post($id)
    {
        $gr = GoodsReceipt::with('items', 'purchaseOrder')->findOrFail($id);

        if ($gr->status !== 'draft') {
            return response()->json([
                'message' => 'LPB sudah diposting atau dibatalkan'
            ], 422);
        }

        try {
            DB::transaction(function () use ($gr) {
                foreach ($gr->items as $item) {
                    $quantity = $item->quantity_actual; // Gunakan qty actual setelah QC

                    // Update stock & movement berdasarkan tipe (raw_material atau product)
                    if ($item->raw_material_id) {
                        // Raw Material Stock
                        $stock = RawMaterialStock::firstOrCreate(
                            [
                                'raw_material_id' => $item->raw_material_id,
                                'warehouse_id' => $gr->warehouse_id,
                            ],
                            ['quantity' => 0]
                        );

                        $stock->increment('quantity', $quantity);

                        // Movement untuk raw material
                        RawMaterialStockMovement::create([
                            'raw_material_id' => $item->raw_material_id,
                            'warehouse_id' => $gr->warehouse_id,
                            'movement_type' => 'IN',
                            'quantity' => $quantity,
                            'reference_type' => GoodsReceipt::class,
                            'reference_id' => $gr->id,
                            'notes' => 'Penerimaan barang dari PO #' . $gr->purchaseOrder->kode,
                            'created_by' => Auth::id(),
                        ]);

                    } else if ($item->product_id) {
                        // Product Stock
                        $stock = Stock::firstOrCreate(
                            [
                                'product_id' => $item->product_id,
                                'warehouse_id' => $gr->warehouse_id,
                            ],
                            ['quantity' => 0]
                        );

                        $stock->increment('quantity', $quantity);

                        // Movement untuk product
                        StockMovement::create([
                            'product_id' => $item->product_id,
                            'warehouse_id' => $gr->warehouse_id,
                            'type' => 'in',
                            'quantity' => $quantity,
                            'reference_type' => 'goods_receipt',
                            'reference_id' => $gr->id,
                            'notes' => 'Penerimaan barang dari PO #' . $gr->purchaseOrder->kode,
                            'created_by' => Auth::id(),
                        ]);
                    }
                }

                // Update GR status
                $gr->update([
                    'status' => 'posted',
                    'posted_by' => Auth::id(),
                    'posted_at' => now(),
                ]);

                // Update PO status
                $gr->purchaseOrder->update(['status' => 'received']);
            });

            return response()->json([
                'message' => 'LPB berhasil diposting dan stock diupdate'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal posting LPB',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE - Cancel/Delete LPB
     */
    public function destroy($id)
    {
        $gr = GoodsReceipt::findOrFail($id);

        if ($gr->status === 'posted') {
            return response()->json([
                'message' => 'LPB yang sudah diposting tidak bisa dihapus'
            ], 422);
        }

        $gr->delete();

        return response()->json([
            'message' => 'LPB berhasil dihapus'
        ]);
    }

    /**
     * POST - Restore LPB
     */
    public function restore($id)
    {
        GoodsReceipt::withTrashed()->findOrFail($id)->restore();

        return response()->json([
            'message' => 'LPB berhasil direstore'
        ]);
    }
}