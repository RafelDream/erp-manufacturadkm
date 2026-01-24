<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\StockRequest;
use App\Models\StockRequestItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockRequestController extends Controller
{
    /**
     * List stock requests
     */
    public function index()
    {
        $data = StockRequest::with(['requester', 'items.product'])
            ->latest()
            ->paginate(10);

        return response()->json($data);
    }

    /**
     * Create stock request
     */
    public function store(Request $request)
    {
        $request->validate([
            'request_date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:1',
        ]);

        DB::beginTransaction();

        try {
            $stockRequest = StockRequest::create([
                'request_number' => 'SR-' . now()->format('YmdHis'),
                'request_date' => $request->request_date,
                'request_by' => Auth::id(),
                'status' => 'draft',
                'notes' => $request->notes,
            ]);

            foreach ($request->items as $item) {
                StockRequestItem::create([
                    'stock_request_id' => $stockRequest->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Permintaan barang berhasil dibuat',
                'data' => $stockRequest->load('items.product')
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal membuat permintaan barang',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show stock request detail
     */
    public function show($id)
    {
        $data = StockRequest::with(['requester', 'items.product'])
            ->findOrFail($id);

        return response()->json($data);
    }

    /**
     * Update stock request (ONLY DRAFT)
     */
    public function update(Request $request, $id)
    {
        $stockRequest = StockRequest::findOrFail($id);

        if ($stockRequest->status !== 'draft') {
            return response()->json([
                'message' => 'Permintaan tidak bisa diubah'
            ], 422);
        }

        $request->validate([
            'request_date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:1',
        ]);

        DB::beginTransaction();

        try {
            $stockRequest->update([
                'request_date' => $request->request_date,
                'notes' => $request->notes,
            ]);

            // refresh items
            $stockRequest->items()->delete();

            foreach ($request->items as $item) {
                StockRequestItem::create([
                    'stock_request_id' => $stockRequest->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Permintaan barang berhasil diperbarui',
                'data' => $stockRequest->load('items.product')
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal memperbarui permintaan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete stock request (soft delete)
     */
    public function destroy($id)
    {
        $stockRequest = StockRequest::findOrFail($id);

        if ($stockRequest->status === 'approved') {
            return response()->json([
                'message' => 'Permintaan yang sudah disetujui tidak dapat dihapus'
            ], 422);
        }

        $stockRequest->delete();

        return response()->json([
            'message' => 'Permintaan barang berhasil dihapus'
        ]);
    }

    /**
     * Restore stock request
     */
    public function restore($id)
    {
        $stockRequest = StockRequest::withTrashed()->findOrFail($id);
        $stockRequest->restore();

        return response()->json([
            'message' => 'Permintaan barang berhasil direstore'
        ]);
    }
}
