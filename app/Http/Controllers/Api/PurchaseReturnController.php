<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseReturn;
use App\Models\PurchaseOrder;
use App\Models\GoodsReceipt;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\RawMaterialStock;
use App\Models\RawMaterialStockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseReturnController extends Controller
{
    /**
     * GET - List Retur Pembelian (RP)
     */
    public function index(Request $request)
    {
        $query = PurchaseReturn::with([
            'items.rawMaterial',
            'items.product',
            'items.unit',
            'purchaseOrder.supplier',
            'goodsReceipt',
            'warehouse',
            'creator'
        ]);

        // Filter by date range
        if ($request->start_date && $request->end_date) {
            $query->whereBetween('return_date', [$request->start_date, $request->end_date]);
        }

        // Filter by status
        if ($request->status) {
            $query->where('status', $request->status);
        }

        // Filter by supplier
        if ($request->supplier_id) {
            $query->whereHas('purchaseOrder', function ($q) use ($request) {
                $q->where('supplier_id', $request->supplier_id);
            });
        }

        return response()->json($query->latest()->get());
    }

    /**
     * GET - Detail Retur Pembelian
     */
    public function show($id)
    {
        return response()->json(
            PurchaseReturn::with([
                'items.rawMaterial',
                'items.product',
                'items.unit',
                'purchaseOrder.supplier',
                'goodsReceipt',
                'warehouse',
                'creator',
                'poster'
            ])->findOrFail($id)
        );
    }

    /**
     * POST - Create Retur Pembelian (Draft)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'goods_receipt_id' => 'nullable|exists:goods_receipts,id',
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'return_date' => 'required|date',
            'delivery_note_number' => 'nullable|string',
            'vehicle_number' => 'nullable|string',
            'reason' => 'required|string',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.raw_material_id' => 'nullable|exists:raw_materials,id',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.unit_id' => 'required|exists:units,id',
            'items.*.quantity_return' => 'required|numeric|min:0.001',
            'items.*.reason' => 'nullable|string',
            'items.*.notes' => 'nullable|string',
        ]);

        // Validasi PO
        $po = PurchaseOrder::with('supplier')->findOrFail($validated['purchase_order_id']);
        
        if (!in_array($po->status, ['received', 'closed'])) {
            return response()->json([
                'message' => 'Hanya PO yang sudah diterima yang bisa diretur'
            ], 422);
        }

        try {
            $return = DB::transaction(function () use ($validated, $po) {
                // Create Purchase Return
                $return = PurchaseReturn::create([
                    'return_number' => 'RP-' . now()->format('YmdHis'),
                    'return_date' => $validated['return_date'],
                    'purchase_order_id' => $po->id,
                    'goods_receipt_id' => $validated['goods_receipt_id'] ?? null,
                    'warehouse_id' => $validated['warehouse_id'],
                    'delivery_note_number' => $validated['delivery_note_number'] ?? null,
                    'vehicle_number' => $validated['vehicle_number'] ?? null,
                    'reason' => $validated['reason'],
                    'status' => 'draft',
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => Auth::id(),
                ]);

                // Create Return Items
                foreach ($validated['items'] as $itemData) {
                    // Validasi: harus pilih salah satu (raw_material atau product)
                    if (!isset($itemData['raw_material_id']) && !isset($itemData['product_id'])) {
                        throw new \Exception('Item harus memiliki raw_material_id atau product_id');
                    }

                    $return->items()->create([
                        'raw_material_id' => $itemData['raw_material_id'] ?? null,
                        'product_id' => $itemData['product_id'] ?? null,
                        'unit_id' => $itemData['unit_id'],
                        'quantity_return' => $itemData['quantity_return'],
                        'reason' => $itemData['reason'] ?? null,
                        'notes' => $itemData['notes'] ?? null,
                    ]);
                }

                return $return;
            });

            return response()->json([
                'message' => 'Retur Pembelian berhasil dibuat (draft)',
                'data' => $return->load('items.rawMaterial', 'items.product', 'purchaseOrder.supplier')
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal membuat Retur Pembelian',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT - Update Retur (only draft)
     */
    public function update(Request $request, $id)
    {
        $return = PurchaseReturn::findOrFail($id);

        if ($return->status !== 'draft') {
            return response()->json([
                'message' => 'Hanya retur draft yang bisa diupdate'
            ], 422);
        }

        $validated = $request->validate([
            'return_date' => 'required|date',
            'warehouse_id' => 'required|exists:warehouses,id',
            'delivery_note_number' => 'nullable|string',
            'vehicle_number' => 'nullable|string',
            'reason' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $return->update($validated);

        return response()->json([
            'message' => 'Retur Pembelian berhasil diupdate'
        ]);
    }

    /**
     * POST - Submit Retur (kirim ke supplier)
     */
    public function submit($id)
    {
        $return = PurchaseReturn::with('items')->findOrFail($id);

        if ($return->status !== 'draft') {
            return response()->json([
                'message' => 'Hanya retur draft yang bisa disubmit'
            ], 422);
        }

        // Validasi: harus ada items
        if ($return->items->count() === 0) {
            return response()->json([
                'message' => 'Retur harus memiliki minimal 1 item'
            ], 422);
        }

        $return->update([
            'status' => 'pending',
            'submitted_by' => Auth::id(),
            'submitted_at' => now(),
        ]);

        return response()->json([
            'message' => 'Retur berhasil disubmit, menunggu persetujuan supplier'
        ]);
    }

    /**
     * POST - Approve Retur (dari supplier)
     */
    public function approve($id)
    {
        $return = PurchaseReturn::findOrFail($id);

        if ($return->status !== 'pending') {
            return response()->json([
                'message' => 'Hanya retur pending yang bisa diapprove'
            ], 422);
        }

        $return->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Retur berhasil disetujui supplier'
        ]);
    }

    /**
     * POST - Reject Retur
     */
    public function reject($id)
    {
        $return = PurchaseReturn::findOrFail($id);

        if ($return->status !== 'pending') {
            return response()->json([
                'message' => 'Hanya retur pending yang bisa ditolak'
            ], 422);
        }

        $return->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Retur ditolak oleh supplier'
        ]);
    }

    /**
     * POST - Realisasi Retur (potong stock + create movement)
     */
    public function realize($id)
    {
        $return = PurchaseReturn::with('items')->findOrFail($id);

        if ($return->status !== 'approved') {
            return response()->json([
                'message' => 'Retur harus approved terlebih dahulu'
            ], 422);
        }

        try {
            DB::transaction(function () use ($return) {
                foreach ($return->items as $item) {
                    $quantity = $item->quantity_return;

                    // Update stock & movement berdasarkan tipe
                    if ($item->raw_material_id) {
                        // Raw Material Stock
                        $stock = RawMaterialStock::where('raw_material_id', $item->raw_material_id)
                            ->where('warehouse_id', $return->warehouse_id)
                            ->lockForUpdate()
                            ->first();

                        if (!$stock || $stock->quantity < $quantity) {
                            throw new \Exception('Stok raw material tidak mencukupi untuk retur');
                        }

                        $stock->decrement('quantity', $quantity);

                        // Movement OUT untuk raw material
                        RawMaterialStockMovement::create([
                            'raw_material_id' => $item->raw_material_id,
                            'warehouse_id' => $return->warehouse_id,
                            'movement_type' => 'OUT',
                            'quantity' => $quantity,
                            'reference_type' => PurchaseReturn::class,
                            'reference_id' => $return->id,
                            'notes' => 'Retur pembelian ke supplier - ' . $return->return_number,
                            'created_by' => Auth::id(),
                        ]);

                    } else if ($item->product_id) {
                        // Product Stock
                        $stock = Stock::where('product_id', $item->product_id)
                            ->where('warehouse_id', $return->warehouse_id)
                            ->lockForUpdate()
                            ->first();

                        if (!$stock || $stock->quantity < $quantity) {
                            throw new \Exception('Stok product tidak mencukupi untuk retur');
                        }

                        $stock->decrement('quantity', $quantity);

                        // Movement OUT untuk product
                        StockMovement::create([
                            'product_id' => $item->product_id,
                            'warehouse_id' => $return->warehouse_id,
                            'type' => 'out',
                            'quantity' => $quantity,
                            'reference_type' => 'purchase_return',
                            'reference_id' => $return->id,
                            'notes' => 'Retur pembelian ke supplier - ' . $return->return_number,
                            'created_by' => Auth::id(),
                        ]);
                    }
                }

                // Update Return status
                $return->update([
                    'status' => 'realized',
                    'realized_by' => Auth::id(),
                    'realized_at' => now(),
                ]);
            });

            return response()->json([
                'message' => 'Retur berhasil direalisasi, stock telah dikurangi'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal merealisasi retur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST - Complete Retur (barang sudah dikirim ke supplier)
     */
    public function complete($id)
    {
        $return = PurchaseReturn::findOrFail($id);

        if ($return->status !== 'realized') {
            return response()->json([
                'message' => 'Retur harus sudah direalisasi terlebih dahulu'
            ], 422);
        }

        $return->update([
            'status' => 'completed',
            'completed_by' => Auth::id(),
            'completed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Retur selesai, barang sudah dikirim ke supplier'
        ]);
    }

    /**
     * DELETE - Cancel/Delete Retur
     */
    public function destroy($id)
    {
        $return = PurchaseReturn::findOrFail($id);

        if (in_array($return->status, ['realized', 'completed'])) {
            return response()->json([
                'message' => 'Retur yang sudah direalisasi tidak bisa dihapus'
            ], 422);
        }

        $return->delete();

        return response()->json([
            'message' => 'Retur berhasil dihapus'
        ]);
    }

    /**
     * POST - Restore Retur
     */
    public function restore($id)
    {
        PurchaseReturn::withTrashed()->findOrFail($id)->restore();

        return response()->json([
            'message' => 'Retur berhasil direstore'
        ]);
    }

    /**
     * GET - Daftar PO yang bisa diretur
     */
    public function getReturnablePurchaseOrders()
    {
        $pos = PurchaseOrder::with('supplier', 'items.rawMaterial', 'items.product')
            ->whereIn('status', ['received', 'closed'])
            ->latest()
            ->get();

        return response()->json($pos);
    }

    /**
     * GET - Items dari GR yang bisa diretur
     */
    public function getReturnableItems($goodsReceiptId)
    {
        $gr = GoodsReceipt::with('items.rawMaterial', 'items.product', 'items.unit')
            ->where('status', 'posted')
            ->findOrFail($goodsReceiptId);

        return response()->json([
            'goods_receipt' => $gr,
            'items' => $gr->items
        ]);
    }
}