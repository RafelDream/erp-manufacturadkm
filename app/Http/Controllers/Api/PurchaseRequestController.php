<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseRequestController extends Controller
{
    public function index()
    {
        return response()->json(
            PurchaseRequest::with(['items.rawMaterial', 'items.product', 'creator', 'requester', 'purchaseOrder'])
                ->latest()
                ->get()
        );
    }

    public function show($id)
    {
        return response()->json(
            PurchaseRequest::with(['items.rawMaterial', 'items.product', 'creator', 'requester', 'purchaseOrder'])
                ->findOrFail($id)
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'request_date' => 'required|date',
            'type'         => 'required|in:raw_materials,product',
            'department'   => 'nullable|string',
            'notes'        => 'nullable|string',
        ]);

        try {
            $pr = DB::transaction(function () use ($validated) {
                return PurchaseRequest::create([
                    'kode'         => 'PR-' . now()->format('YmdHis'),
                    'request_date' => $validated['request_date'],
                    'type'         => $validated['type'],
                    'department'   => $validated['department'] ?? null,
                    'request_by'   => Auth::id(),
                    'status'       => 'draft',
                    'notes'        => $validated['notes'] ?? null,
                    'created_by'   => Auth::id(),
                ]);
            });

            return response()->json([
                'message' => 'Purchase Request berhasil dibuat',
                'data'    => $pr
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal membuat PR',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $pr = PurchaseRequest::findOrFail($id);

        if ($pr->status !== 'draft') {
            return response()->json([
                'message' => 'Hanya draft PR yang bisa diupdate'
            ], 422);
        }

        $validated = $request->validate([
            'request_date' => 'required|date',
            'type'         => 'required|in:raw_materials,product',
            'department'   => 'nullable|string',
            'notes'        => 'nullable|string',
        ]);

        $pr->update($validated);

        return response()->json([
            'message' => 'Purchase Request berhasil diupdate',
        ]);
    }

    public function destroy($id)
    {
        $pr = PurchaseRequest::findOrFail($id);
        
        if ($pr->purchaseOrder) {
            return response()->json([
                'message' => 'PR yang sudah memiliki PO tidak dapat dihapus'
            ], 422);
        }

        $pr->delete();

        return response()->json([
            'message' => 'Purchase Request berhasil dihapus'
        ]);
    }

    public function restore($id)
    {
        PurchaseRequest::withTrashed()
            ->findOrFail($id)
            ->restore();

        return response()->json([
            'message' => 'Purchase Request berhasil dikembalikan'
        ]);
    }

    public function submit($id)
    {
        $pr = PurchaseRequest::findOrFail($id);

        if ($pr->status !== 'draft') {
            return response()->json([
                'message' => 'Hanya draft PR yang bisa disubmit'
            ], 422);
        }

        $pr->update(['status' => 'submitted']);

        return response()->json([
            'message' => 'Purchase Request berhasil disubmit'
        ]);
    }

    public function approve($id)
    {
        $pr = PurchaseRequest::findOrFail($id);

        if ($pr->status !== 'submitted') {
            return response()->json([
                'message' => 'Hanya submitted PR yang bisa diapprove'
            ], 422);
        }

        $pr->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Purchase Request berhasil diapprove'
        ]);
    }

    public function reject($id)
    {
        $pr = PurchaseRequest::findOrFail($id);

        if ($pr->status !== 'submitted') {
            return response()->json([
                'message' => 'Hanya submitted PR yang bisa direject'
            ], 422);
        }

        $pr->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Purchase Request ditolak'
        ]);
    }
}