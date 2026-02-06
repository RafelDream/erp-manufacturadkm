<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceReceiptReportController extends Controller
{
    /**
     * Laporan Tanda Terima Faktur - Summary
     * Menampilkan ringkasan tanda terima faktur per periode
     */
    public function index(Request $request): JsonResponse
    {
        $start = $request->start_date;
        $end = $request->end_date;
        $supplierId = $request->supplier_id;
        $status = $request->status;
        $requesterId = $request->requester_id;

        $query = DB::table('invoice_receipts')
            ->join('suppliers', 'invoice_receipts.supplier_id', '=', 'suppliers.id')
            ->join('purchase_orders', 'invoice_receipts.purchase_order_id', '=', 'purchase_orders.id')
            ->join('users as requester', 'invoice_receipts.requester_id', '=', 'requester.id')
            ->leftJoin('invoices', 'invoice_receipts.id', '=', 'invoices.invoice_receipt_id')
            ->select(
                'invoice_receipts.id as receipt_id',
                'invoice_receipts.receipt_number',
                'invoice_receipts.transaction_date',
                'invoice_receipts.status',
                'suppliers.nama as supplier_name',
                'purchase_orders.kode as po_number',
                'requester.name as requester_name',
                DB::raw("COUNT(DISTINCT invoices.id) as total_invoices"),
                DB::raw("COALESCE(SUM(invoices.amount), 0) as total_amount"),
                'invoice_receipts.created_at',
                'invoice_receipts.submitted_at',
                'invoice_receipts.approved_at'
            );

        // Filter by date range
        if ($start && $end) {
            $query->whereBetween('invoice_receipts.transaction_date', [$start, $end]);
        }

        // Filter by supplier
        if ($supplierId) {
            $query->where('invoice_receipts.supplier_id', $supplierId);
        }

        // Filter by status
        if ($status) {
            $query->where('invoice_receipts.status', $status);
        }

        // Filter by requester
        if ($requesterId) {
            $query->where('invoice_receipts.requester_id', $requesterId);
        }

        $data = $query
            ->groupBy(
                'invoice_receipts.id',
                'invoice_receipts.receipt_number',
                'invoice_receipts.transaction_date',
                'invoice_receipts.status',
                'suppliers.nama',
                'purchase_orders.kode',
                'requester.name',
                'invoice_receipts.created_at',
                'invoice_receipts.submitted_at',
                'invoice_receipts.approved_at'
            )
            ->orderBy('invoice_receipts.transaction_date', 'desc')
            ->get();

        $data->transform(function ($row) {
            $row->total_invoices = (int) $row->total_invoices;
            $row->total_amount = (float) $row->total_amount;
            return $row;
        });

        // Calculate summary
        $summary = [
            'total_receipts' => $data->count(),
            'total_draft' => $data->where('status', 'draft')->count(),
            'total_submitted' => $data->where('status', 'submitted')->count(),
            'total_approved' => $data->where('status', 'approved')->count(),
            'total_rejected' => $data->where('status', 'rejected')->count(),
            'grand_total_amount' => $data->sum('total_amount'),
        ];

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'summary' => $summary,
            'meta' => [
                'start_date' => $start,
                'end_date' => $end,
            ],
        ]);
    }

    /**
     * Detail Tanda Terima Faktur
     * Menampilkan detail lengkap termasuk semua invoice di dalamnya
     */
    public function detail($receiptId): JsonResponse
    {
        $query = DB::table('invoice_receipts')
            ->join('suppliers', 'invoice_receipts.supplier_id', '=', 'suppliers.id')
            ->join('purchase_orders', 'invoice_receipts.purchase_order_id', '=', 'purchase_orders.id')
            ->join('users as requester', 'invoice_receipts.requester_id', '=', 'requester.id')
            ->leftJoin('users as creator', 'invoice_receipts.created_by', '=', 'creator.id')
            ->leftJoin('users as submitter', 'invoice_receipts.submitted_by', '=', 'submitter.id')
            ->leftJoin('users as approver', 'invoice_receipts.approved_by', '=', 'approver.id')
            ->select(
                'invoice_receipts.*',
                'suppliers.nama as supplier_name',
                'suppliers.alamat as supplier_address',
                'suppliers.telepon as supplier_phone',
                'suppliers.email as supplier_email',
                'purchase_orders.kode as po_number',
                'purchase_orders.order_date as po_date',
                'requester.name as requester_name',
                'creator.name as creator_name',
                'submitter.name as submitter_name',
                'approver.name as approver_name'
            )
            ->where('invoice_receipts.id', $receiptId)
            ->first();

        if (!$query) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tanda Terima Faktur tidak ditemukan'
            ], 404);
        }

        // Get invoice items
        $invoices = DB::table('invoices')
            ->select(
                'id',
                'invoice_number',
                'invoice_date',
                'due_date',
                'amount',
                'notes',
                'created_at'
            )
            ->where('invoice_receipt_id', $receiptId)
            ->orderBy('invoice_date')
            ->get();

        $invoices->transform(function ($row) {
            $row->amount = (float) $row->amount;
            return $row;
        });

        $result = [
            'receipt' => $query,
            'invoices' => $invoices,
            'summary' => [
                'total_invoices' => $invoices->count(),
                'total_amount' => $invoices->sum('amount'),
            ]
        ];

        return response()->json([
            'status' => 'success',
            'data' => $result,
        ]);
    }

    /**
     * Laporan Per Supplier
     * Summary tanda terima faktur per supplier
     */
    public function bySupplier(Request $request): JsonResponse
    {
        $start = $request->start_date;
        $end = $request->end_date;

        $query = DB::table('suppliers')
            ->leftJoin('invoice_receipts', 'suppliers.id', '=', 'invoice_receipts.supplier_id')
            ->leftJoin('invoices', 'invoice_receipts.id', '=', 'invoices.invoice_receipt_id')
            ->select(
                'suppliers.id as supplier_id',
                'suppliers.nama as supplier_name',
                'suppliers.alamat as supplier_address',
                'suppliers.telepon as supplier_phone',
                DB::raw("COUNT(DISTINCT invoice_receipts.id) as total_receipts"),
                DB::raw("COUNT(DISTINCT CASE WHEN invoice_receipts.status = 'draft' THEN invoice_receipts.id END) as draft_count"),
                DB::raw("COUNT(DISTINCT CASE WHEN invoice_receipts.status = 'submitted' THEN invoice_receipts.id END) as submitted_count"),
                DB::raw("COUNT(DISTINCT CASE WHEN invoice_receipts.status = 'approved' THEN invoice_receipts.id END) as approved_count"),
                DB::raw("COUNT(DISTINCT CASE WHEN invoice_receipts.status = 'rejected' THEN invoice_receipts.id END) as rejected_count"),
                DB::raw("COUNT(invoices.id) as total_invoices"),
                DB::raw("COALESCE(SUM(invoices.amount), 0) as total_amount")
            );

        // Filter by date range
        if ($start && $end) {
            $query->whereBetween('invoice_receipts.transaction_date', [$start, $end]);
        }

        $data = $query
            ->where('suppliers.is_active', true)
            ->groupBy(
                'suppliers.id',
                'suppliers.nama',
                'suppliers.alamat',
                'suppliers.telepon'
            )
            ->orderBy('total_amount', 'desc')
            ->get();

        $data->transform(function ($row) {
            $row->total_receipts = (int) $row->total_receipts;
            $row->draft_count = (int) $row->draft_count;
            $row->submitted_count = (int) $row->submitted_count;
            $row->approved_count = (int) $row->approved_count;
            $row->rejected_count = (int) $row->rejected_count;
            $row->total_invoices = (int) $row->total_invoices;
            $row->total_amount = (float) $row->total_amount;
            return $row;
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'meta' => [
                'start_date' => $start,
                'end_date' => $end,
                'total_suppliers' => $data->count(),
                'grand_total' => $data->sum('total_amount'),
            ],
        ]);
    }

    /**
     * Laporan Invoice yang Jatuh Tempo
     * Menampilkan invoice yang sudah/akan jatuh tempo
     */
    public function dueInvoices(Request $request): JsonResponse
    {
        $daysAhead = $request->days_ahead ?? 30; // Default 30 hari ke depan
        $status = $request->status; // Filter status receipt

        $query = DB::table('invoices')
            ->join('invoice_receipts', 'invoices.invoice_receipt_id', '=', 'invoice_receipts.id')
            ->join('suppliers', 'invoice_receipts.supplier_id', '=', 'suppliers.id')
            ->join('purchase_orders', 'invoice_receipts.purchase_order_id', '=', 'purchase_orders.id')
            ->select(
                'invoices.id as invoice_id',
                'invoices.invoice_number',
                'invoices.invoice_date',
                'invoices.due_date',
                'invoices.amount',
                'invoice_receipts.receipt_number',
                'invoice_receipts.status as receipt_status',
                'suppliers.nama as supplier_name',
                'suppliers.telepon as supplier_phone',
                'purchase_orders.kode as po_number',
                DB::raw("DATEDIFF(invoices.due_date, CURDATE()) as days_remaining"),
                DB::raw("CASE 
                    WHEN DATEDIFF(invoices.due_date, CURDATE()) < 0 THEN 'overdue'
                    WHEN DATEDIFF(invoices.due_date, CURDATE()) <= 7 THEN 'urgent'
                    WHEN DATEDIFF(invoices.due_date, CURDATE()) <= 14 THEN 'soon'
                    ELSE 'normal'
                END as urgency_level")
            )
            ->whereRaw("DATEDIFF(invoices.due_date, CURDATE()) <= ?", [$daysAhead]);

        // Filter by receipt status
        if ($status) {
            $query->where('invoice_receipts.status', $status);
        }

        $data = $query
            ->orderBy('invoices.due_date', 'asc')
            ->get();

        $data->transform(function ($row) {
            $row->amount = (float) $row->amount;
            $row->days_remaining = (int) $row->days_remaining;
            return $row;
        });

        // Group by urgency
        $summary = [
            'overdue' => $data->where('urgency_level', 'overdue')->count(),
            'urgent' => $data->where('urgency_level', 'urgent')->count(),
            'soon' => $data->where('urgency_level', 'soon')->count(),
            'normal' => $data->where('urgency_level', 'normal')->count(),
            'total_invoices' => $data->count(),
            'total_amount' => $data->sum('amount'),
            'overdue_amount' => $data->where('urgency_level', 'overdue')->sum('amount'),
        ];

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'summary' => $summary,
            'meta' => [
                'days_ahead' => $daysAhead,
            ],
        ]);
    }

    /**
     * Laporan Aging Invoice
     * Menampilkan umur invoice (berapa lama sudah lewat jatuh tempo)
     */
    public function agingReport(Request $request): JsonResponse
    {
        $query = DB::table('invoices')
            ->join('invoice_receipts', 'invoices.invoice_receipt_id', '=', 'invoice_receipts.id')
            ->join('suppliers', 'invoice_receipts.supplier_id', '=', 'suppliers.id')
            ->select(
                'suppliers.nama as supplier_name',
                'invoices.invoice_number',
                'invoices.invoice_date',
                'invoices.due_date',
                'invoices.amount',
                'invoice_receipts.receipt_number',
                'invoice_receipts.status',
                DB::raw("DATEDIFF(CURDATE(), invoices.due_date) as days_overdue"),
                DB::raw("CASE 
                    WHEN DATEDIFF(CURDATE(), invoices.due_date) <= 0 THEN 'not_due'
                    WHEN DATEDIFF(CURDATE(), invoices.due_date) <= 30 THEN '1-30_days'
                    WHEN DATEDIFF(CURDATE(), invoices.due_date) <= 60 THEN '31-60_days'
                    WHEN DATEDIFF(CURDATE(), invoices.due_date) <= 90 THEN '61-90_days'
                    ELSE 'over_90_days'
                END as aging_category")
            )
            ->orderBy('days_overdue', 'desc')
            ->get();

        $query->transform(function ($row) {
            $row->amount = (float) $row->amount;
            $row->days_overdue = (int) $row->days_overdue;
            return $row;
        });

        // Summary by aging category
        $agingSummary = [
            'not_due' => [
                'count' => $query->where('aging_category', 'not_due')->count(),
                'amount' => $query->where('aging_category', 'not_due')->sum('amount'),
            ],
            '1_30_days' => [
                'count' => $query->where('aging_category', '1-30_days')->count(),
                'amount' => $query->where('aging_category', '1-30_days')->sum('amount'),
            ],
            '31_60_days' => [
                'count' => $query->where('aging_category', '31-60_days')->count(),
                'amount' => $query->where('aging_category', '31-60_days')->sum('amount'),
            ],
            '61_90_days' => [
                'count' => $query->where('aging_category', '61-90_days')->count(),
                'amount' => $query->where('aging_category', '61-90_days')->sum('amount'),
            ],
            'over_90_days' => [
                'count' => $query->where('aging_category', 'over_90_days')->count(),
                'amount' => $query->where('aging_category', 'over_90_days')->sum('amount'),
            ],
        ];

        return response()->json([
            'status' => 'success',
            'data' => $query,
            'aging_summary' => $agingSummary,
            'meta' => [
                'total_invoices' => $query->count(),
                'total_amount' => $query->sum('amount'),
            ],
        ]);
    }

    /**
     * Laporan Monthly Trend
     * Tren tanda terima faktur per bulan
     */
    public function monthlyTrend(Request $request): JsonResponse
    {
        $start = $request->start_date;
        $end = $request->end_date;

        $query = DB::table('invoice_receipts')
            ->join('invoices', 'invoice_receipts.id', '=', 'invoices.invoice_receipt_id')
            ->select(
                DB::raw("DATE_FORMAT(invoice_receipts.transaction_date, '%Y-%m') as month"),
                DB::raw("COUNT(DISTINCT invoice_receipts.id) as total_receipts"),
                DB::raw("COUNT(invoices.id) as total_invoices"),
                DB::raw("SUM(invoices.amount) as total_amount"),
                DB::raw("COUNT(DISTINCT CASE WHEN invoice_receipts.status = 'approved' THEN invoice_receipts.id END) as approved_count")
            );

        if ($start && $end) {
            $query->whereBetween('invoice_receipts.transaction_date', [$start, $end]);
        }

        $data = $query
            ->groupBy(DB::raw("DATE_FORMAT(invoice_receipts.transaction_date, '%Y-%m')"))
            ->orderBy('month', 'asc')
            ->get();

        $data->transform(function ($row) {
            $row->total_receipts = (int) $row->total_receipts;
            $row->total_invoices = (int) $row->total_invoices;
            $row->total_amount = (float) $row->total_amount;
            $row->approved_count = (int) $row->approved_count;
            return $row;
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'meta' => [
                'start_date' => $start,
                'end_date' => $end,
                'total_months' => $data->count(),
                'grand_total' => $data->sum('total_amount'),
            ],
        ]);
    }

    /**
     * Laporan Per Requester
     * Summary berdasarkan user yang request
     */
    public function byRequester(Request $request): JsonResponse
    {
        $start = $request->start_date;
        $end = $request->end_date;

        $query = DB::table('users')
            ->leftJoin('invoice_receipts', 'users.id', '=', 'invoice_receipts.requester_id')
            ->leftJoin('invoices', 'invoice_receipts.id', '=', 'invoices.invoice_receipt_id')
            ->select(
                'users.id as requester_id',
                'users.name as requester_name',
                'users.email as requester_email',
                DB::raw("COUNT(DISTINCT invoice_receipts.id) as total_receipts"),
                DB::raw("COUNT(DISTINCT CASE WHEN invoice_receipts.status = 'approved' THEN invoice_receipts.id END) as approved_receipts"),
                DB::raw("COUNT(DISTINCT CASE WHEN invoice_receipts.status = 'rejected' THEN invoice_receipts.id END) as rejected_receipts"),
                DB::raw("COUNT(invoices.id) as total_invoices"),
                DB::raw("COALESCE(SUM(invoices.amount), 0) as total_amount")
            );

        if ($start && $end) {
            $query->whereBetween('invoice_receipts.transaction_date', [$start, $end]);
        }

        $data = $query
            ->groupBy('users.id', 'users.name', 'users.email')
            ->having('total_receipts', '>', 0)
            ->orderBy('total_receipts', 'desc')
            ->get();

        $data->transform(function ($row) {
            $row->total_receipts = (int) $row->total_receipts;
            $row->approved_receipts = (int) $row->approved_receipts;
            $row->rejected_receipts = (int) $row->rejected_receipts;
            $row->total_invoices = (int) $row->total_invoices;
            $row->total_amount = (float) $row->total_amount;
            return $row;
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'meta' => [
                'start_date' => $start,
                'end_date' => $end,
                'total_requesters' => $data->count(),
            ],
        ]);
    }
}