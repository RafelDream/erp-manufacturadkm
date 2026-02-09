<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InitialBalance;
use App\Models\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InitialBalanceController extends Controller
{
    /**
     * GET - List Saldo Awal per Tahun
     */
    public function index(Request $request)
    {
        $query = InitialBalance::with(['account', 'creator']);

        // Filter by year
        if ($request->year) {
            $query->where('year', $request->year);
        }

        // Filter by status
        if ($request->status) {
            $query->where('status', $request->status);
        }

        return response()->json($query->orderBy('year', 'desc')->get());
    }

    /**
     * GET - Detail Saldo Awal by Year
     */
    public function show($year)
    {
        $balances = InitialBalance::with(['account'])
            ->where('year', $year)
            ->get();

        return response()->json([
            'year' => $year,
            'status' => $balances->first()->status ?? 'draft',
            'balances' => $balances,
            'total_debit' => $balances->sum('debit'),
            'total_credit' => $balances->sum('credit'),
        ]);
    }

    /**
     * POST - Create/Update Saldo Awal for a Year
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'items' => 'required|array|min:1',
            'items.*.account_id' => 'required|exists:chart_of_accounts,id',
            'items.*.debit' => 'required|numeric|min:0',
            'items.*.credit' => 'required|numeric|min:0',
        ]);

        // Check if already has approved balance for this year
        $existing = InitialBalance::where('year', $validated['year'])
            ->where('status', 'approved')
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Saldo awal tahun ' . $validated['year'] . ' sudah approved'
            ], 422);
        }

        try {
            DB::transaction(function () use ($validated) {
                // Delete existing draft for this year
                InitialBalance::where('year', $validated['year'])
                    ->where('status', 'draft')
                    ->delete();

                // Create new balances
                foreach ($validated['items'] as $item) {
                    InitialBalance::create([
                        'year' => $validated['year'],
                        'account_id' => $item['account_id'],
                        'debit' => $item['debit'],
                        'credit' => $item['credit'],
                        'budget' => $item['budget'] ?? 0,
                        'status' => 'draft',
                        'created_by' => Auth::id(),
                    ]);
                }
            });

            return response()->json([
                'message' => 'Saldo awal tahun ' . $validated['year'] . ' berhasil dibuat (draft)'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal membuat saldo awal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST - Approve Saldo Awal
     */
    public function approve($year)
    {
        $balances = InitialBalance::where('year', $year)
            ->where('status', 'draft')
            ->get();

        if ($balances->isEmpty()) {
            return response()->json([
                'message' => 'Tidak ada saldo awal draft untuk tahun ' . $year
            ], 422);
        }

        // Validate: Debit harus = Credit
        $totalDebit = $balances->sum('debit');
        $totalCredit = $balances->sum('credit');

        if ($totalDebit != $totalCredit) {
            return response()->json([
                'message' => 'Total Debit (' . number_format($totalDebit, 0, ',', '.') . 
                           ') tidak sama dengan Total Credit (' . number_format($totalCredit, 0, ',', '.') . ')',
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
            ], 422);
        }

        InitialBalance::where('year', $year)
            ->where('status', 'draft')
            ->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

        return response()->json([
            'message' => 'Saldo awal tahun ' . $year . ' berhasil diapprove'
        ]);
    }

    /**
     * DELETE - Delete Saldo Awal (only draft)
     */
    public function destroy($year)
    {
        $deleted = InitialBalance::where('year', $year)
            ->where('status', 'draft')
            ->delete();

        if ($deleted === 0) {
            return response()->json([
                'message' => 'Tidak ada saldo awal draft untuk dihapus'
            ], 422);
        }

        return response()->json([
            'message' => 'Saldo awal tahun ' . $year . ' berhasil dihapus'
        ]);
    }

    /**
     * GET - Get available years
     */
    public function getYears()
    {
        $years = InitialBalance::select('year', 'status')
            ->groupBy('year', 'status')
            ->orderBy('year', 'desc')
            ->get()
            ->groupBy('year')
            ->map(function ($items) {
                return [
                    'year' => $items->first()->year,
                    'status' => $items->first()->status,
                ];
            })
            ->values();

        return response()->json($years);
    }
}