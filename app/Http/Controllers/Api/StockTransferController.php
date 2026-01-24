<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\StockTransfer;
use App\Models\Stock;
use App\Models\StockMovement;
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
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
        ]);

        DB::transaction(function () use ($data) {
            $transfer = StockTransfer::create([
                'kode' => 'TRF-' . time(),
                'dari_warehouse_id' => $data['dari_warehouse_id'],
                'ke_warehouse_id' => $data['ke_warehouse_id'],
                'transfer_date' => $data['transfer_date'],
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            foreach ($data['items'] as $item) {
                $transfer->items()->create($item);
            }
        });

        return response()->json(['message' => 'Transfer dibuat']);
    }

    public function approve($id)
    {
        $transfer = StockTransfer::findOrFail($id);

        if ($transfer->status !== 'draft') {
            return response()->json(['message'=>'Status tidak valid'],422);
        }

        $transfer->update([
            'status'=>'approved',
            'approved_by'=>Auth::id()
        ]);

        return response()->json(['message'=>'Transfer approved']);
    }

    public function execute($id)
    {
        $transfer = StockTransfer::with('items')->findOrFail($id);

        if ($transfer->status !== 'approved') {
            return response()->json(['message'=>'Belum approved'],422);
        }

        DB::transaction(function () use ($transfer) {
            foreach ($transfer->items as $item) {

                // FROM
                $from = Stock::where('product_id',$item->product_id)
                    ->where('warehouse_id',$transfer->dari_warehouse_id)
                    ->lockForUpdate()
                    ->first();

                if (!$from || $from->quantity < $item->quantity) {
                    throw new \Exception('Stock tidak mencukupi');
                }

                $from->decrement('quantity',$item->quantity);

                // TO (FIXED)
                $to = Stock::firstOrCreate(
                    [
                        'product_id'=>$item->product_id,
                        'warehouse_id'=>$transfer->ke_warehouse_id
                    ],
                    ['quantity'=>0]
                );
                $to->increment('quantity',$item->quantity);

                // movement OUT
                StockMovement::create([
                    'product_id'=>$item->product_id,
                    'warehouse_id'=>$transfer->dari_warehouse_id,
                    'type'=>'out',
                    'quantity'=>$item->quantity,
                    'reference_type'=>'transfer',
                    'reference_id'=>$transfer->id,
                    'notes'=>'Transfer keluar',
                    'created_by'=>Auth::id()
                ]);

                // movement IN
                StockMovement::create([
                    'product_id'=>$item->product_id,
                    'warehouse_id'=>$transfer->ke_warehouse_id,
                    'type'=>'in',
                    'quantity'=>$item->quantity,
                    'reference_type'=>'transfer',
                    'reference_id'=>$transfer->id,
                    'notes'=>'Transfer masuk',
                    'created_by'=>Auth::id()
                ]);
            }

            $transfer->update(['status'=>'executed']);
        });

        return response()->json(['message'=>'Transfer selesai']);
    }

    public function reject($id)
    {
        StockTransfer::where('id',$id)
            ->where('status','draft')
            ->update(['status'=>'rejected']);

        return response()->json(['message'=>'Transfer ditolak']);
    }
}
