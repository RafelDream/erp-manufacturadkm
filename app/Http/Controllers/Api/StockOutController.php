<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockOut;
use App\Models\StockRequest;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class StockOutController extends Controller
{
    public function index()
    {
        return response()->json(
            StockOut::with(['items.product', 'stockRequest', 'creator', 'warehouse'])
                ->latest()
                ->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'stock_request_id' => 'required|exists:stock_requests,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'out_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $stockRequest = StockRequest::with('items')->findOrFail($validated['stock_request_id']);

        if ($stockRequest->status !== 'approved') {
            return response()->json([
                'message' => 'Stock request belum disetujui'
            ], 422);
        }

        if ($stockRequest->completed_at) {
            return response()->json([
                'message' => 'Stock request sudah pernah diproses'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $stockOut = StockOut::create([
                'stock_request_id' => $stockRequest->id,
                'warehouse_id' => $validated['warehouse_id'],
                'out_date' => $validated['out_date'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            foreach ($stockRequest->items as $item) {
                $stock = Stock::where('product_id', $item->product_id)
                    ->where('warehouse_id', $validated['warehouse_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$stock || $stock->quantity < $item->quantity) {
                    throw new \Exception('Stock tidak mencukupi untuk produk ID ' . $item->product_id);
                }

                $stockOut->items()->create([
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                ]);

                $stock->decrement('quantity', $item->quantity);

                StockMovement::create([
                    'product_id' => $item->product_id,
                    'warehouse_id' => $validated['warehouse_id'],
                    'type' => 'out',
                    'quantity' => $item->quantity,
                    'reference_type' => 'stock_out',
                    'reference_id' => $stockOut->id,
                    'notes' => 'Pengeluaran barang dari stock request #' . $stockRequest->request_number,
                    'created_by' => Auth::id(),
                ]);
            }

            // ✅✅✅ INI YANG KURANG - UPDATE STATUS STOCK REQUEST ✅✅✅
            $stockRequest->update([
                'status' => 'completed',
                'completed_at' => now(),
                'completed_by' => Auth::id(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Stock out berhasil dibuat dan stock request telah diselesaikan',
                'data' => $stockOut->load('items.product', 'stockRequest')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal membuat stock out',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $stockOut = StockOut::with('items', 'stockRequest')->findOrFail($id);

            foreach ($stockOut->items as $item) {
                // 1. Kembalikan stok yang tadinya keluar
                $stock = Stock::where('product_id', $item->product_id)
                    ->where('warehouse_id', $stockOut->warehouse_id)
                    ->lockForUpdate()
                    ->first();

                if ($stock) {
                    $stock->increment('quantity', $item->quantity);
                }

                // 2. Hapus movement terkait
                StockMovement::where('reference_type', 'stock_out')
                    ->where('reference_id', $stockOut->id)
                    ->where('product_id', $item->product_id)
                    ->delete();
            }

            if ($stockOut->stockRequest) {
                $stockOut->stockRequest->update([
                    'status' => 'approved',
                    'completed_at' => null,
                    'completed_by' => null,
                ]);
            }

            $stockOut->delete();

            DB::commit();

            return response()->json([
                'message' => 'Stock out berhasil dihapus, stok dikembalikan, dan stock request di-reset'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal menghapus stock out',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}