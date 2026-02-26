<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use App\Models\SalesOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class WorkOrderController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'sales_order_id' => 'required|exists:sales_orders,id',
            'tanggal' => 'required|date',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|numeric',
        ]);

        return DB::transaction(function () use ($request) {
            $noWo = 'WO-' . date('Ymd') . '-' . strtoupper(Str::random(4));

            $wo = WorkOrder::create([
                'no_wo' => $noWo,
                'sales_order_id' => $request->sales_order_id,
                'tanggal' => $request->tanggal,
                'status' => 'processed',
                'created_by' => Auth::id() ?? 1,
            ]);

            foreach ($request->items as $item) {
                $wo->items()->create([
                    'product_id' => $item['product_id'],
                    'qty_to_process' => $item['qty'],
                ]);
            }

            return response()->json(['success' => true, 'data' => $wo->load('items')], 201);
        });
    }

    /**
    * Update Data Header WO (PUT)
    */
    public function update(Request $request, $id)
    {
    $wo = WorkOrder::find($id);

    if (!$wo) {
        return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
    }

    $request->validate([
        'tanggal' => 'sometimes|date',
        'status' => 'sometimes|in:draft,processed,completed,cancelled',
        'notes' => 'nullable|string'
    ]);

    $wo->update($request->only(['tanggal', 'status', 'notes']));

    return response()->json([
        'success' => true,
        'message' => 'Work Order berhasil diperbarui',
        'data' => $wo
    ]);
}

    /**
    * Hapus WO sementara (DELETE)
    */
    public function destroy($id)
    {
    $wo = WorkOrder::find($id);

    if (!$wo) {
        return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
    }

    $wo->delete(); // Menggunakan SoftDeletes

    return response()->json([
        'success' => true,
        'message' => 'Work Order berhasil dipindahkan ke sampah'
    ]);
    }

    /**
     * Mengembalikan WO dari sampah (POST)
     */
    public function restore($id)
    {
    $wo = WorkOrder::onlyTrashed()->find($id);

    if (!$wo) {
        return response()->json(['success' => false, 'message' => 'Data tidak ditemukan di sampah'], 404);
    }

    $wo->restore();

    return response()->json([
        'success' => true,
        'message' => 'Work Order berhasil dikembalikan',
        'data' => $wo
    ]);
    }

    public function getSpkItems($id)
    {
    $spk = SalesOrder::withTrashed()->with('items.product')->find($id);

    if (!$spk) {
        return response()->json([
            'success' => false,
            'message' => 'Surat Pesanan Konsumen tidak ditemukan'
        ], 404);
    }

    $items = $spk->items->map(function ($item) {
        return [
            'product_id' => $item->product_id,
            'product_name' => $item->product->name,
            'qty_pesanan' => $item->qty_pesanan,
            // Secara default, qty yang diproses disamakan dengan qty pesanan
            'qty_to_process' => $item->qty_pesanan, 
        ];
        });

        return response()->json([
        'success' => true,
        'data' => [
            'no_spk' => $spk->no_spk,
            'customer_name' => $spk->customer->name ?? '-',
            'items' => $items
        ]
        ]);
    }
}
