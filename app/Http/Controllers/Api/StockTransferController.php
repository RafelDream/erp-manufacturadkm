<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\StockTransfer;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\RawMaterialStock;
use App\Models\RawMaterialStockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class StockTransferController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'dari_warehouse_id' => 'required|exists:warehouses,id',
            'ke_warehouse_id' => 'required|exists:warehouses,id|different:dari_warehouse_id',
            'transfer_date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.raw_material_id' => 'nullable|exists:raw_materials,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
        ]);

            foreach ($data['items'] as $item) {
                $hasProduct = array_key_exists('product_id', $item) && !empty($item['product_id']);
                $hasRM = array_key_exists('raw_material_id', $item) && !empty($item['raw_material_id']);

                if (($hasProduct && $hasRM) || (!$hasProduct && !$hasRM)) {
                        return response()->json([
                        'message' => 'Setiap item wajib memiliki product_id ATAU raw_material_id (pilih salah satu)',
                        'debug_info' => [
                        'has_product' => $hasProduct,
                        'has_rm' => $hasRM,
                        'item_received' => $item // Ini untuk melacak baris mana yang bikin error
                        ]
                    ], 422);
                }
            }

        return DB::transaction(function () use ($data) {
            $transfer = StockTransfer::create([
                'kode' => 'TRF-' . time(),
                'dari_warehouse_id' => $data['dari_warehouse_id'],
                'ke_warehouse_id' => $data['ke_warehouse_id'],
                'transfer_date' => $data['transfer_date'],
                'notes' => $data['notes'] ?? null,
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]);

            foreach ($data['items'] as $item) {
                $itemableId = $item['product_id'] ?? $item['raw_material_id'];
                $itemableType = !empty($item['product_id']) ? Product::class : RawMaterial::class;
                $transfer->items()->create([
                    'itemable_id' => $itemableId,
                    'itemable_type' => $itemableType,
                    'quantity' => $item['quantity']
                ]);
            }

        return response()->json(['message' => 'Transfer dibuat', 'data' => $transfer->load('items')], 201);
        });
    }

    public function approve($id)
    {
        $transfer = StockTransfer::findOrFail($id);

        if ($transfer->status !== 'draft') {
            return response()->json(['message'=>'Status tidak valid'],422);
            $transfer->update(['status' => 'approved', 'approved_by' => Auth::id()]);
            return response()->json(['message' => 'Transfer approved']);
        }

        $transfer->update([
            'status'=>'approved',
            'approved_by'=>Auth::id()
        ]);

        return response()->json(['message'=>'Transfer approved']);
    }

    public function execute($id)
    {
        $transfer = StockTransfer::with('items.itemable')->findOrFail($id);

        if ($transfer->status !== 'approved') {
            return response()->json(['message'=>'Belum approved'],422);
        }
        try {
        DB::transaction(function () use ($transfer) {
            foreach ($transfer->items as $item) {

                // FROM
            if ($item->itemable_type === Product::class) {
                $productId = $item->itemable_id;
                $from = Stock::where('product_id', $productId)
                    ->where('warehouse_id',$transfer->dari_warehouse_id)
                    ->lockForUpdate()
                    ->first();

                if (!$from || $from->quantity < $item->quantity) {
                    throw new \Exception("Stock Product ID {$productId} tidak mencukupi");
                }

                $from->decrement('quantity',$item->quantity);

                // TO (FIXED)
                $to = Stock::firstOrCreate(
                    [
                        'product_id'=>$productId,
                        'warehouse_id'=>$transfer->ke_warehouse_id
                    ],
                    ['quantity'=>0]
                );
                $to->increment('quantity',$item->quantity);

                // movement OUT &IN
                $this->logProductMovement($transfer, $item);
            }

            // Raw Material

            if ($item->itemable_type === RawMaterial::class) {
                $rmId = $item->itemable_id;

                $fromRM = RawMaterialStock::where('raw_material_id', $rmId)
                    ->where('warehouse_id',$transfer->dari_warehouse_id)
                    ->lockForUpdate()
                    ->first();

                if (!$fromRM || $fromRM->quantity < $item->quantity) {
                    throw new \Exception("Stock raw material ID {$rmId} tidak mencukupi");
                }
                $fromRM->decrement('quantity',$item->quantity);

                $toRM = RawMaterialStock::firstOrCreate(
                    [
                        'raw_material_id'=>$rmId,
                        'warehouse_id'=>$transfer->ke_warehouse_id
                    ],
                    ['quantity'=>0]
                );
                $toRM   ->increment('quantity',$item->quantity);

            // Movement OUT & IN Raw Material
                $this->logRMMovement($transfer, $item);
            }
        }

            $transfer->update(['status'=>'executed']);
    });

        return response()->json(['message'=>'Transfer selesai']);
    } catch (\Exception $e) {
        return response()->json(['message' => $e->getMessage()], 422);
    }
}

    // Helper untuk merapikan Log Movement Produk
    private function logProductMovement($transfer, $item) {
        $common = [
            'product_id' => $item->itemable_id,
            'quantity' => $item->quantity,
            'reference_type' => 'transfer',
            'reference_id' => $transfer->id,
            'created_by' => Auth::id()
        ];

        StockMovement::create(array_merge($common, ['warehouse_id' => $transfer->dari_warehouse_id, 'type' => 'out', 'notes' => 'Transfer Keluar']));
        StockMovement::create(array_merge($common, ['warehouse_id' => $transfer->ke_warehouse_id, 'type' => 'in', 'notes' => 'Transfer Masuk']));
    }

    // Helper untuk merapikan Log Movement Raw Material
    private function logRMMovement($transfer, $item) {
        $common = [
            'raw_material_id' => $item->itemable_id,
            'quantity' => $item->quantity,
            'reference_type' => 'stock_transfer',
            'reference_id' => $transfer->id,
            'created_by' => Auth::id()
        ];

        RawMaterialStockMovement::create(array_merge($common, ['warehouse_id' => $transfer->dari_warehouse_id, 'movement_type' => 'TRANSFER_OUT']));
        RawMaterialStockMovement::create(array_merge($common, ['warehouse_id' => $transfer->ke_warehouse_id, 'movement_type' => 'TRANSFER_IN']));
    }

    public function reject($id)
    {
        $transfer = StockTransfer::findOrFail($id);
        if ($transfer->status !== 'draft') return response()->json(['message' => 'Hanya draft yang bisa ditolak'], 422);
        $transfer->update(['status' => 'rejected']);

        return response()->json(['message'=>'Transfer ditolak']);
    }
}

