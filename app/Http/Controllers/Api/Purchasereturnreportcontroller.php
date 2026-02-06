<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseReturnReportController extends Controller
{
    /**
     * Laporan Retur Pembelian - Summary
     * Menampilkan ringkasan retur pembelian per periode
     */
    public function index(Request $request): JsonResponse
    {
        $start = $request->start_date;
        $end = $request->end_date;
        $supplierId = $request->supplier_id;
        $status = $request->status;
        $warehouseId = $request->warehouse_id;

        $query = DB::table('purchase_returns')
            ->join('purchase_orders', 'purchase_returns.purchase_order_id', '=', 'purchase_orders.id')
            ->join('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
            ->join('warehouses', 'purchase_returns.warehouse_id', '=', 'warehouses.id')
            ->leftJoin('users as creator', 'purchase_returns.created_by', '=', 'creator.id')
            ->leftJoin('purchase_return_items', 'purchase_returns.id', '=', 'purchase_return_items.purchase_return_id')
            ->select(
                'purchase_returns.id as return_id',
                'purchase_returns.return_number',
                'purchase_returns.return_date',
                'purchase_returns.status',
                'purchase_returns.reason',
                'suppliers.nama as supplier_name',
                'purchase_orders.kode as po_number',
                'warehouses.name as warehouse_name',
                'creator.name as creator_name',
                DB::raw("COUNT(DISTINCT purchase_return_items.id) as total_items"),
                DB::raw("COALESCE(SUM(purchase_return_items.quantity_return), 0) as total_quantity"),
                'purchase_returns.created_at',
                'purchase_returns.submitted_at',
                'purchase_returns.approved_at',
                'purchase_returns.realized_at'
            );

        // Filter by date range
        if ($start && $end) {
            $query->whereBetween('purchase_returns.return_date', [$start, $end]);
        }

        // Filter by supplier
        if ($supplierId) {
            $query->where('purchase_orders.supplier_id', $supplierId);
        }

        // Filter by status
        if ($status) {
            $query->where('purchase_returns.status', $status);
        }

        // Filter by warehouse
        if ($warehouseId) {
            $query->where('purchase_returns.warehouse_id', $warehouseId);
        }

        $data = $query
            ->groupBy(
                'purchase_returns.id',
                'purchase_returns.return_number',
                'purchase_returns.return_date',
                'purchase_returns.status',
                'purchase_returns.reason',
                'suppliers.nama',
                'purchase_orders.kode',
                'warehouses.name',
                'creator.name',
                'purchase_returns.created_at',
                'purchase_returns.submitted_at',
                'purchase_returns.approved_at',
                'purchase_returns.realized_at'
            )
            ->orderBy('purchase_returns.return_date', 'desc')
            ->get();

        $data->transform(function ($row) {
            $row->total_items = (int) $row->total_items;
            $row->total_quantity = (float) $row->total_quantity;
            return $row;
        });

        // Calculate summary
        $summary = [
            'total_returns' => $data->count(),
            'total_draft' => $data->where('status', 'draft')->count(),
            'total_pending' => $data->where('status', 'pending')->count(),
            'total_approved' => $data->where('status', 'approved')->count(),
            'total_rejected' => $data->where('status', 'rejected')->count(),
            'total_realized' => $data->where('status', 'realized')->count(),
            'total_completed' => $data->where('status', 'completed')->count(),
            'grand_total_quantity' => $data->sum('total_quantity'),
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
     * Detail Retur Pembelian
     * Menampilkan detail lengkap termasuk semua item yang diretur
     */
    public function detail($returnId): JsonResponse
    {
        $return = DB::table('purchase_returns')
            ->join('purchase_orders', 'purchase_returns.purchase_order_id', '=', 'purchase_orders.id')
            ->join('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
            ->join('warehouses', 'purchase_returns.warehouse_id', '=', 'warehouses.id')
            ->leftJoin('goods_receipts', 'purchase_returns.goods_receipt_id', '=', 'goods_receipts.id')
            ->leftJoin('users as creator', 'purchase_returns.created_by', '=', 'creator.id')
            ->leftJoin('users as submitter', 'purchase_returns.submitted_by', '=', 'submitter.id')
            ->leftJoin('users as approver', 'purchase_returns.approved_by', '=', 'approver.id')
            ->leftJoin('users as realizer', 'purchase_returns.realized_by', '=', 'realizer.id')
            ->select(
                'purchase_returns.*',
                'suppliers.nama as supplier_name',
                'suppliers.alamat as supplier_address',
                'suppliers.telepon as supplier_phone',
                'purchase_orders.kode as po_number',
                'purchase_orders.order_date as po_date',
                'warehouses.name as warehouse_name',
                'goods_receipts.receipt_number as gr_number',
                'creator.name as creator_name',
                'submitter.name as submitter_name',
                'approver.name as approver_name',
                'realizer.name as realizer_name'
            )
            ->where('purchase_returns.id', $returnId)
            ->first();

        if (!$return) {
            return response()->json([
                'status' => 'error',
                'message' => 'Retur Pembelian tidak ditemukan'
            ], 404);
        }

        // Get return items
        $items = DB::table('purchase_return_items')
            ->leftJoin('raw_materials', 'purchase_return_items.raw_material_id', '=', 'raw_materials.id')
            ->leftJoin('products', 'purchase_return_items.product_id', '=', 'products.id')
            ->leftJoin('units', 'purchase_return_items.unit_id', '=', 'units.id')
            ->select(
                'purchase_return_items.id',
                DB::raw("COALESCE(raw_materials.code, products.kode) as item_code"),
                DB::raw("COALESCE(raw_materials.name, products.name) as item_name"),
                DB::raw("COALESCE(raw_materials.category, products.tipe) as item_category"),
                'units.name as unit_name',
                'purchase_return_items.quantity_return',
                'purchase_return_items.reason',
                'purchase_return_items.notes'
            )
            ->where('purchase_return_items.purchase_return_id', $returnId)
            ->get();

        $items->transform(function ($row) {
            $row->quantity_return = (float) $row->quantity_return;
            return $row;
        });

        $result = [
            'return' => $return,
            'items' => $items,
            'summary' => [
                'total_items' => $items->count(),
                'total_quantity' => $items->sum('quantity_return'),
            ]
        ];

        return response()->json([
            'status' => 'success',
            'data' => $result,
        ]);
    }

    /**
     * Laporan Per Supplier
     * Summary retur pembelian per supplier
     */
    public function bySupplier(Request $request): JsonResponse
    {
        $start = $request->start_date;
        $end = $request->end_date;

        $query = DB::table('suppliers')
            ->leftJoin('purchase_orders', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->leftJoin('purchase_returns', 'purchase_orders.id', '=', 'purchase_returns.purchase_order_id')
            ->leftJoin('purchase_return_items', 'purchase_returns.id', '=', 'purchase_return_items.purchase_return_id')
            ->select(
                'suppliers.id as supplier_id',
                'suppliers.nama as supplier_name',
                'suppliers.alamat as supplier_address',
                'suppliers.telepon as supplier_phone',
                DB::raw("COUNT(DISTINCT purchase_returns.id) as total_returns"),
                DB::raw("COUNT(DISTINCT CASE WHEN purchase_returns.status = 'draft' THEN purchase_returns.id END) as draft_count"),
                DB::raw("COUNT(DISTINCT CASE WHEN purchase_returns.status = 'pending' THEN purchase_returns.id END) as pending_count"),
                DB::raw("COUNT(DISTINCT CASE WHEN purchase_returns.status = 'approved' THEN purchase_returns.id END) as approved_count"),
                DB::raw("COUNT(DISTINCT CASE WHEN purchase_returns.status = 'rejected' THEN purchase_returns.id END) as rejected_count"),
                DB::raw("COUNT(DISTINCT CASE WHEN purchase_returns.status = 'realized' THEN purchase_returns.id END) as realized_count"),
                DB::raw("COUNT(DISTINCT CASE WHEN purchase_returns.status = 'completed' THEN purchase_returns.id END) as completed_count"),
                DB::raw("COUNT(purchase_return_items.id) as total_items"),
                DB::raw("COALESCE(SUM(purchase_return_items.quantity_return), 0) as total_quantity")
            );

        // Filter by date range
        if ($start && $end) {
            $query->whereBetween('purchase_returns.return_date', [$start, $end]);
        }

        $data = $query
            ->where('suppliers.is_active', true)
            ->groupBy(
                'suppliers.id',
                'suppliers.nama',
                'suppliers.alamat',
                'suppliers.telepon'
            )
            ->orderBy('total_returns', 'desc')
            ->get();

        $data->transform(function ($row) {
            $row->total_returns = (int) $row->total_returns;
            $row->draft_count = (int) $row->draft_count;
            $row->pending_count = (int) $row->pending_count;
            $row->approved_count = (int) $row->approved_count;
            $row->rejected_count = (int) $row->rejected_count;
            $row->realized_count = (int) $row->realized_count;
            $row->completed_count = (int) $row->completed_count;
            $row->total_items = (int) $row->total_items;
            $row->total_quantity = (float) $row->total_quantity;
            return $row;
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'meta' => [
                'start_date' => $start,
                'end_date' => $end,
                'total_suppliers' => $data->count(),
                'grand_total_quantity' => $data->sum('total_quantity'),
            ],
        ]);
    }

    /**
     * Laporan Item yang Sering Diretur
     * Top items yang paling banyak diretur
     */
    public function topReturnedItems(Request $request): JsonResponse
    {
        $start = $request->start_date;
        $end = $request->end_date;
        $limit = $request->limit ?? 20;

        $query = DB::table('purchase_return_items')
            ->join('purchase_returns', 'purchase_return_items.purchase_return_id', '=', 'purchase_returns.id')
            ->join('purchase_orders', 'purchase_returns.purchase_order_id', '=', 'purchase_orders.id')
            ->join('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
            ->leftJoin('raw_materials', 'purchase_return_items.raw_material_id', '=', 'raw_materials.id')
            ->leftJoin('products', 'purchase_return_items.product_id', '=', 'products.id')
            ->select(
                DB::raw("COALESCE(raw_materials.code, products.kode) as item_code"),
                DB::raw("COALESCE(raw_materials.name, products.name) as item_name"),
                DB::raw("COALESCE(raw_materials.category, products.tipe) as item_category"),
                DB::raw("COUNT(DISTINCT purchase_returns.id) as return_count"),
                DB::raw("COUNT(DISTINCT suppliers.id) as supplier_count"),
                DB::raw("SUM(purchase_return_items.quantity_return) as total_quantity_returned"),
                DB::raw("GROUP_CONCAT(DISTINCT suppliers.nama SEPARATOR ', ') as suppliers")
            );

        // Filter by date range
        if ($start && $end) {
            $query->whereBetween('purchase_returns.return_date', [$start, $end]);
        }

        $data = $query
            ->whereIn('purchase_returns.status', ['realized', 'completed'])
            ->groupBy(
                DB::raw("COALESCE(raw_materials.code, products.kode)"),
                DB::raw("COALESCE(raw_materials.name, products.name)"),
                DB::raw("COALESCE(raw_materials.category, products.tipe)")
            )
            ->orderBy('return_count', 'desc')
            ->limit($limit)
            ->get();

        $data->transform(function ($row) {
            $row->return_count = (int) $row->return_count;
            $row->supplier_count = (int) $row->supplier_count;
            $row->total_quantity_returned = (float) $row->total_quantity_returned;
            return $row;
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'meta' => [
                'start_date' => $start,
                'end_date' => $end,
                'limit' => $limit,
            ],
        ]);
    }

    /**
     * Laporan Alasan Retur
     * Analisis berdasarkan alasan retur
     */
    public function byReason(Request $request): JsonResponse
    {
        $start = $request->start_date;
        $end = $request->end_date;

        $query = DB::table('purchase_returns')
            ->join('purchase_orders', 'purchase_returns.purchase_order_id', '=', 'purchase_orders.id')
            ->join('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
            ->leftJoin('purchase_return_items', 'purchase_returns.id', '=', 'purchase_return_items.purchase_return_id')
            ->select(
                'purchase_returns.reason',
                DB::raw("COUNT(DISTINCT purchase_returns.id) as return_count"),
                DB::raw("COUNT(DISTINCT suppliers.id) as supplier_count"),
                DB::raw("COUNT(purchase_return_items.id) as total_items"),
                DB::raw("COALESCE(SUM(purchase_return_items.quantity_return), 0) as total_quantity")
            );

        // Filter by date range
        if ($start && $end) {
            $query->whereBetween('purchase_returns.return_date', [$start, $end]);
        }

        $data = $query
            ->whereNotNull('purchase_returns.reason')
            ->groupBy('purchase_returns.reason')
            ->orderBy('return_count', 'desc')
            ->get();

        $data->transform(function ($row) {
            $row->return_count = (int) $row->return_count;
            $row->supplier_count = (int) $row->supplier_count;
            $row->total_items = (int) $row->total_items;
            $row->total_quantity = (float) $row->total_quantity;
            return $row;
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'meta' => [
                'start_date' => $start,
                'end_date' => $end,
                'total_reasons' => $data->count(),
            ],
        ]);
    }

    /**
     * Laporan Monthly Trend
     * Tren retur pembelian per bulan
     */
    public function monthlyTrend(Request $request): JsonResponse
    {
        $start = $request->start_date;
        $end = $request->end_date;

        $query = DB::table('purchase_returns')
            ->leftJoin('purchase_return_items', 'purchase_returns.id', '=', 'purchase_return_items.purchase_return_id')
            ->select(
                DB::raw("DATE_FORMAT(purchase_returns.return_date, '%Y-%m') as month"),
                DB::raw("COUNT(DISTINCT purchase_returns.id) as total_returns"),
                DB::raw("COUNT(DISTINCT CASE WHEN purchase_returns.status = 'completed' THEN purchase_returns.id END) as completed_returns"),
                DB::raw("COUNT(purchase_return_items.id) as total_items"),
                DB::raw("SUM(purchase_return_items.quantity_return) as total_quantity")
            );

        if ($start && $end) {
            $query->whereBetween('purchase_returns.return_date', [$start, $end]);
        }

        $data = $query
            ->groupBy(DB::raw("DATE_FORMAT(purchase_returns.return_date, '%Y-%m')"))
            ->orderBy('month', 'asc')
            ->get();

        $data->transform(function ($row) {
            $row->total_returns = (int) $row->total_returns;
            $row->completed_returns = (int) $row->completed_returns;
            $row->total_items = (int) $row->total_items;
            $row->total_quantity = (float) ($row->total_quantity ?? 0);
            return $row;
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'meta' => [
                'start_date' => $start,
                'end_date' => $end,
                'total_months' => $data->count(),
            ],
        ]);
    }
    /**
     * Laporan Approval Rate
     * Persentase approval vs rejection retur
     */
    public function approvalRate(Request $request): JsonResponse
    {
        $start = $request->start_date;
        $end = $request->end_date;

        $query = DB::table('purchase_returns')
            ->join('purchase_orders', 'purchase_returns.purchase_order_id', '=', 'purchase_orders.id')
            ->join('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
            ->select(
                'suppliers.id as supplier_id',
                'suppliers.nama as supplier_name',
                DB::raw("COUNT(DISTINCT purchase_returns.id) as total_returns"),
                DB::raw("COUNT(DISTINCT CASE WHEN purchase_returns.status IN ('approved', 'realized', 'completed') THEN purchase_returns.id END) as approved_count"),
                DB::raw("COUNT(DISTINCT CASE WHEN purchase_returns.status = 'rejected' THEN purchase_returns.id END) as rejected_count"),
                DB::raw("COUNT(DISTINCT CASE WHEN purchase_returns.status IN ('draft', 'pending') THEN purchase_returns.id END) as pending_count"),
                DB::raw("ROUND(
                    COUNT(DISTINCT CASE WHEN purchase_returns.status IN ('approved', 'realized', 'completed') THEN purchase_returns.id END) * 100.0 / 
                    NULLIF(COUNT(DISTINCT purchase_returns.id), 0), 
                2) as approval_rate")
            );

        if ($start && $end) {
            $query->whereBetween('purchase_returns.return_date', [$start, $end]);
        }

        $data = $query
            ->where('suppliers.is_active', true)
            ->groupBy('suppliers.id', 'suppliers.nama')
            ->orderBy('approval_rate', 'desc')
            ->get();

        $data->transform(function ($row) {
            $row->total_returns = (int) $row->total_returns;
            $row->approved_count = (int) $row->approved_count;
            $row->rejected_count = (int) $row->rejected_count;
            $row->pending_count = (int) $row->pending_count;
            $row->approval_rate = (float) ($row->approval_rate ?? 0);
            return $row;
        });

        // Overall summary
        $summary = [
            'total_suppliers' => $data->count(),
            'total_returns' => $data->sum('total_returns'),
            'total_approved' => $data->sum('approved_count'),
            'total_rejected' => $data->sum('rejected_count'),
            'total_pending' => $data->sum('pending_count'),
            'overall_approval_rate' => $data->sum('total_returns') > 0 
                ? round($data->sum('approved_count') * 100 / $data->sum('total_returns'), 2) 
                : 0,
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
}