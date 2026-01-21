<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StockRequestApprovalController extends Controller
{
    public function approve($id)
    {
        $request = StockRequest::with('items')->findOrFail($id);

        if ($request->status !== 'draft') {
            return response()->json([
                'message' => 'Request sudah diproses'
            ], 422);
        }

        $request->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Stock request berhasil disetujui'
        ]);
    }

    public function reject(Request $request, $id)
    {
        $stockRequest = StockRequest::findOrFail($id);

        if ($stockRequest->status !== 'draft') {
            return response()->json([
                'message' => 'Request sudah diproses'
            ], 422);
        }

        $stockRequest->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Stock request ditolak'
        ]);
    }
}