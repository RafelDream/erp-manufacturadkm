<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LedgerReportController extends Controller
{
    /**
     * GET - Saldo per Akun COA (General Ledger Summary)
     * Menampilkan total debit, kredit, dan saldo berjalan per akun.
     * 
     * Query params:
     *   - type       : filter by asset|liability|equity|revenue|expense
     *   - category   : filter by category (kas_bank, utang_lancar, dll)
     *   - account_id : filter satu akun spesifik
     *   - start_date : filter jurnal dari tanggal
     *   - end_date   : filter jurnal sampai tanggal
     */
    public function summary(Request $request)
    {
        $query = DB::table('journal_entry_lines as jel')
            ->join('chart_of_accounts as coa', 'coa.id', '=', 'jel.account_id')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->where('je.status', 'posted')
            ->whereNull('coa.deleted_at')
            ->select(
                'coa.id as account_id',
                'coa.code',
                'coa.name',
                'coa.type',
                'coa.category',
                DB::raw('SUM(jel.debit) as total_debit'),
                DB::raw('SUM(jel.credit) as total_credit'),
                DB::raw('SUM(jel.debit) - SUM(jel.credit) as saldo')
            );

        if ($request->type) {
            $query->where('coa.type', $request->type);
        }

        if ($request->category) {
            $query->where('coa.category', $request->category);
        }

        if ($request->account_id) {
            $query->where('jel.account_id', $request->account_id);
        }

        if ($request->start_date) {
            $query->where('je.journal_date', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->where('je.journal_date', '<=', $request->end_date);
        }

        $results = $query
            ->groupBy('coa.id', 'coa.code', 'coa.name', 'coa.type', 'coa.category')
            ->orderBy('coa.code')
            ->get();

        // Interpretasi saldo berdasarkan tipe akun
        $results = $results->map(function ($row) {
            // Normal balance:
            // asset & expense  → saldo positif = debit lebih besar (normal)
            // liability, equity, revenue → saldo negatif = kredit lebih besar (normal)
            $row->saldo_normal = match($row->type) {
                'asset', 'expense'              => (float) $row->saldo,
                'liability', 'equity', 'revenue' => (float) ($row->total_credit - $row->total_debit),
            };
            $row->total_debit  = (float) $row->total_debit;
            $row->total_credit = (float) $row->total_credit;
            $row->saldo        = (float) $row->saldo;
            return $row;
        });

        return response()->json([
            'as_of'        => $request->end_date ?? now()->toDateString(),
            'total_debit'  => $results->sum('total_debit'),
            'total_credit' => $results->sum('total_credit'),
            'accounts'     => $results,
        ]);
    }

    /**
     * GET - Buku Besar per Akun (Detail Ledger)
     * Menampilkan semua transaksi jurnal untuk satu akun COA beserta saldo berjalan.
     * 
     * Query params:
     *   - start_date : filter dari tanggal
     *   - end_date   : filter sampai tanggal
     */
    public function detail(Request $request, $accountId)
    {
        $account = ChartOfAccount::findOrFail($accountId);

        $query = JournalEntryLine::with(['journalEntry'])
            ->where('account_id', $accountId)
            ->whereHas('journalEntry', function ($q) {
                $q->where('status', 'posted');
            });

        if ($request->start_date) {
            $query->whereHas('journalEntry', function ($q) use ($request) {
                $q->where('journal_date', '>=', $request->start_date);
            });
        }

        if ($request->end_date) {
            $query->whereHas('journalEntry', function ($q) use ($request) {
                $q->where('journal_date', '<=', $request->end_date);
            });
        }

        $lines = $query->get()->sortBy('journalEntry.journal_date');

        // Hitung saldo berjalan
        $runningBalance = 0;
        $isDebitNormal = in_array($account->type, ['asset', 'expense']);

        $transactions = $lines->map(function ($line) use (&$runningBalance, $isDebitNormal) {
            if ($isDebitNormal) {
                $runningBalance += $line->debit - $line->credit;
            } else {
                $runningBalance += $line->credit - $line->debit;
            }

            return [
                'journal_number' => $line->journalEntry->journal_number,
                'journal_date'   => $line->journalEntry->journal_date,
                'description'    => $line->description ?? $line->journalEntry->description,
                'debit'          => (float) $line->debit,
                'credit'         => (float) $line->credit,
                'saldo'          => (float) $runningBalance,
            ];
        })->values();

        return response()->json([
            'account' => [
                'id'       => $account->id,
                'code'     => $account->code,
                'name'     => $account->name,
                'type'     => $account->type,
                'category' => $account->category,
            ],
            'period' => [
                'start_date' => $request->start_date ?? '-',
                'end_date'   => $request->end_date ?? now()->toDateString(),
            ],
            'summary' => [
                'total_debit'  => (float) $lines->sum('debit'),
                'total_credit' => (float) $lines->sum('credit'),
                'saldo_akhir'  => (float) $runningBalance,
            ],
            'transactions' => $transactions,
        ]);
    }

    /**
     * GET - Neraca Saldo (Trial Balance)
     * Semua akun COA yang punya transaksi, dikelompokkan per tipe.
     */
    public function trialBalance(Request $request)
    {
        $rows = DB::table('journal_entry_lines as jel')
            ->join('chart_of_accounts as coa', 'coa.id', '=', 'jel.account_id')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->where('je.status', 'posted')
            ->whereNull('coa.deleted_at')
            ->select(
                'coa.id',
                'coa.code',
                'coa.name',
                'coa.type',
                'coa.category',
                DB::raw('SUM(jel.debit) as total_debit'),
                DB::raw('SUM(jel.credit) as total_credit')
            );

        if ($request->start_date) {
            $rows->where('je.journal_date', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $rows->where('je.journal_date', '<=', $request->end_date);
        }

        $rows = $rows
            ->groupBy('coa.id', 'coa.code', 'coa.name', 'coa.type', 'coa.category')
            ->orderBy('coa.code')
            ->get();

        $grouped = $rows->groupBy('type')->map(function ($items, $type) {
            return $items->map(function ($row) {
                return [
                    'code'         => $row->code,
                    'name'         => $row->name,
                    'category'     => $row->category,
                    'total_debit'  => (float) $row->total_debit,
                    'total_credit' => (float) $row->total_credit,
                ];
            })->values();
        });

        return response()->json([
            'as_of'        => $request->end_date ?? now()->toDateString(),
            'total_debit'  => (float) $rows->sum('total_debit'),
            'total_credit' => (float) $rows->sum('total_credit'),
            'balanced'     => round($rows->sum('total_debit'), 2) === round($rows->sum('total_credit'), 2),
            'by_type'      => $grouped,
        ]);
    }
}