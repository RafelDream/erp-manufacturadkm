<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChartOfAccountController extends Controller
{
    /**
     * GET List COA
     */
    public function index(Request $request)
    {
        $query = ChartOfAccount::query();

        // Filter Account Type
        if ($request->type) {
            $query->where('type', $request->type);
        }

        // Filter by category
        if ($request->category) {
            $query->where('category', $request->category);
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return response()->json($query->orderBy('code')->get());
    }

    /**
     * GET - Detail COA
     */
    public function show($id)
    {
        return response()->json(
            ChartOfAccount::findOrFail($id)
        );
    }

    /**
     * POST - Create COA
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:chart_of_accounts,code',
            'name' => 'required|string',
            'type' => 'required|in:asset,liability,equity,revenue,expense',
            'category' => 'required|string',
            'is_cash' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $coa = ChartOfAccount::create(array_merge($validated, [
            'created_by' => Auth::id()
        ]));

        return response()->json([
            'message' => 'Chart of Account berhasil dibuat',
            'data' => $coa
        ], 201);
    }

    /**
     * PUT - Update COA
     */
    public function update(Request $request, $id)
    {
        $coa = ChartOfAccount::findOrFail($id);

        $validated = $request->validate([
            'code' => 'nullable|string|unique:chart_of_accounts,code,' . $id,
            'name' => 'nullable|string',
            'type' => 'nullable|in:asset,liability,equity,revenue,expense',
            'category' => 'nullable|string',
            'is_cash' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $coa->update($validated);

        return response()->json([
            'message' => 'Chart of Account berhasil diupdate',
            'data' => $coa
        ]);
    }

    /**
     * DELETE - Soft delete COA
     */
    public function destroy($id)
    {
        $coa = ChartOfAccount::findOrFail($id);
        $coa->delete();

        return response()->json([
            'message' => 'Chart of Account berhasil dihapus'
        ]);
    }

    /**
     * POST - Restore COA
     */
    public function restore($id)
    {
        ChartOfAccount::withTrashed()->findOrFail($id)->restore();

        return response()->json([
            'message' => 'Chart of Account berhasil direstore'
        ]);
    }

    /**
     * GET - Get grouped COA by type
     */
    public function getGrouped()
    {
        $accounts = ChartOfAccount::where('is_active', true)
            ->orderBy('code')
            ->get()
            ->groupBy('type');

        return response()->json($accounts);
    }

    /**
     * GET - Get cash accounts only
     */
    public function getCashAccounts()
    {
        $accounts = ChartOfAccount::where('is_cash', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return response()->json($accounts);
    }
}