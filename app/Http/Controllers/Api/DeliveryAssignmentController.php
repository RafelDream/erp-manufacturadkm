<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAssignment;
use App\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class DeliveryAssignmentController extends Controller
{
    public function show($id)
    {
        // Mengambil data dengan relasi agar informasi lengkap
        $spkp = DeliveryAssignment::with([
            'workOrder.salesOrder.customer',
            'workOrder.items.product'
        ])->find($id);

        if (!$spkp) {
            return response()->json([
                'success' => false,
                'message' => 'Data penugasan tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $spkp
        ]);
    }


    public function index(Request $request)
    {
        $data = DeliveryAssignment::with(['workOrder.salesOrder.customer'])
                ->latest()
                ->paginate($request->per_page ?? 10);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'work_order_id' => 'required|exists:work_orders,id',
            'driver_name' => 'required|string',
            'vehicle_plate_number' => 'required|string',
            'tanggal_kirim' => 'required|date',
        ]);

        $noSpkp = 'SPKP-' . date('Ymd') . '-' . strtoupper(Str::random(4));

        $spkp = DeliveryAssignment::create([
            'no_spkp' => $noSpkp,
            'work_order_id' => $request->work_order_id,
            'driver_name' => $request->driver_name,
            'vehicle_plate_number' => $request->vehicle_plate_number,
            'tanggal_kirim' => $request->tanggal_kirim,
            'status' => 'pending',
            'created_by' => Auth::id() ?? 1,
        ]);

        return response()->json(['success' => true, 'data' => $spkp], 201);
    }

    public function update(Request $request, $id)
    {
    $spkp = DeliveryAssignment::find($id);

    if (!$spkp) {
        return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
    }

    $request->validate([
        'driver_name' => 'sometimes|string',
        'vehicle_plate_number' => 'sometimes|string',
        'tanggal_kirim' => 'sometimes|date',
        'status' => 'sometimes|in:pending,on_process,in_transit,completed,cancelled',
        'notes' => 'nullable|string'
    ]);

    $spkp->update($request->only([
        'driver_name', 
        'vehicle_plate_number', 
        'tanggal_kirim', 
        'status', 
        'notes'
    ]));

    return response()->json([
        'success' => true,
        'message' => 'Data SPKP berhasil diperbarui',
        'data' => $spkp
    ]);
    }

    /**
    * Hapus SPKP sementara (DELETE)
    */
    public function destroy($id)
    {
    $spkp = DeliveryAssignment::find($id);

    if (!$spkp) {
        return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
    }

    $spkp->delete(); // Soft Delete

    return response()->json([
        'success' => true,
        'message' => 'Data SPKP berhasil dihapus sementara'
    ]);
    }

    /**
    * Mengembalikan SPKP dari sampah (POST/RESTORE)
    */
    public function restore($id)
    {
    $spkp = DeliveryAssignment::onlyTrashed()->find($id);

    if (!$spkp) {
        return response()->json(['success' => false, 'message' => 'Data tidak ditemukan di sampah'], 404);
    }

    $spkp->restore();

    return response()->json([
        'success' => true,
        'message' => 'Data SPKP berhasil dikembalikan',
        'data' => $spkp
    ]);
    }

    public function getWoDetails($id)
{
    // Eager loading ke items dan product
    $wo = WorkOrder::with(['items.product', 'salesOrder.customer'])->find($id);

    if (!$wo) {
        return response()->json([
            'success' => false,
            'message' => 'Work Order tidak ditemukan'
        ], 404);
    }

    return response()->json([
        'success' => true,
        'data' => [
            'no_wo' => $wo->no_wo,
            'customer_name' => $wo->salesOrder->customer->name ?? '-',
            'tanggal_wo' => $wo->tanggal,
            'items' => $wo->items->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name,
                    'qty_to_ship' => $item->qty_to_process,
                ];
            })
        ]
    ]);
    }

    public function updateStatus(Request $request, $id)
    {
    $request->validate([
        'status' => 'required|in:pending,on_process,in_transit,completed,cancelled'
    ]);

    $spkp = DeliveryAssignment::find($id);
    if (!$spkp) return response()->json(['message' => 'Data tidak ditemukan'], 404);

    $spkp->update(['status' => $request->status]);

    return response()->json([
        'success' => true,
        'message' => 'Status pengiriman diperbarui menjadi ' . $request->status
    ]);
    }
}
