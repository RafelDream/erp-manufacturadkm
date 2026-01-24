<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockRequest;
use Illuminate\Support\Facades\Auth;

class StockRequestApprovalController extends Controller
{
    /**
     * Approve stock request
     */
    public function approve($id)
    {
        $stockRequest = StockRequest::with('items')->findOrFail($id);

        if ($stockRequest->status !== 'draft') {
            return response()->json([
                'message' => 'Request sudah diproses'
            ], 422);
        }

        $stockRequest->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Stock request berhasil disetujui'
        ]);
    }

    /**
     * Reject stock request
     */
    public function reject($id)
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
