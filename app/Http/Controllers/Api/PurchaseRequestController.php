<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseRequestController extends Controller
{
    /**
     * GET - list PR
     */
    public function index()
    {
        return response()->json(
            PurchaseRequest::with(['items', 'creator', 'requester'])
                ->latest()
                ->get()
        );
    }

    /**
     * GET - detail PR
     */
    public function show($id)
    {
        return response()->json(
            PurchaseRequest::with(['items', 'creator', 'requester'])
                ->findOrFail($id)
        );
    }

    /**
     * POST - create PR
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'request_date' => 'required|date',
            'type'         => 'required|string',
            'department'   => 'nullable|string',
            'request_by' => 'required|exists:users,id',
            'notes'        => 'nullable|string',
        ]);

        $pr = DB::transaction(function () use ($validated) {
            return PurchaseRequest::create([
                'kode'         => 'PR-' . now()->format('YmdHis'),
                'request_date' => $validated['request_date'],
                'type'         => $validated['type'],
                'department'   => $validated['department'] ?? null,
                'request_by' => $validated['request_by'],
                'status'       => 'draft',
                'notes'        => $validated['notes'] ?? null,
                'created_by'   => Auth::id(),
            ]);
        });

        return response()->json([
            'message' => 'Purchase Request berhasil dibuat',
            'data'    => $pr
        ], 201);
    }

    /**
     * PUT - update PR (selama masih DRAFT)
     */
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
            'department'   => 'nullable|string',
            'notes'        => 'nullable|string',
        ]);

        $pr->update($validated);

        return response()->json([
            'message' => 'Purchase Request berhasil diupdate',
        ]);
    }

    /**
     * DELETE - soft delete PR
     */
    public function destroy($id)
    {
        PurchaseRequest::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Purchase Request berhasil dihapus'
        ]);
    }

    /**
     * RESTORE - restore PR
     */
    public function restore($id)
    {
        PurchaseRequest::withTrashed()
            ->findOrFail($id)
            ->restore();

        return response()->json([
            'message' => 'Purchase Request berhasil dikembalikan'
        ]);
    }

    /**
     * POST - submit PR
     */
    public function submit($id)
    {
        $pr = PurchaseRequest::findOrFail($id);

        if ($pr->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft PR can be submitted'
            ], 422);
        }

        $pr->update(['status' => 'SUBMITTED']);

        return response()->json([
            'message' => 'Purchase Request submitted'
        ]);
    }
    public function approve($id)
    {
        $pr = PurchaseRequest::findOrFail($id);

        if ($pr->status !== 'submitted') {
            return response()->json([
                'message' => 'Only submitted PR can be approved'
            ], 422);
        }

        $pr->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Purchase Request approved'
        ]);
    }
    public function reject($id)
    {
        $pr = PurchaseRequest::findOrFail($id);

        if ($pr->status !== 'submitted') {
            return response()->json([
                'message' => 'Only submitted PR can be rejected'
            ], 422);
        }

        $pr->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Purchase Request rejected'
        ]);
    }
}
