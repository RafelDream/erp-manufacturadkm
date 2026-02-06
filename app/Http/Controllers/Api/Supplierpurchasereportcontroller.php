<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierPurchaseReportController extends Controller
{
    /**
     * Laporan Pembelian per Supplier
     * Menampilkan total pembelian, jumlah PO, dan detail transaksi per supplier
     */
    public function index(Request $request): JsonResponse
    {
        $start = $request->start_date;
        $end = $request->end_date;
        $supplierId = $request->supplier_id;
        $status = $request->status;

        $query = DB::table('suppliers')
            ->leftJoin('purchase_orders', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->leftJoin('purchase_order_items', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->select(
                'suppliers.id as supplier_id',
                'suppliers.nama as supplier_name',
                'suppliers.alamat as supplier_address',
                'suppliers.telepon as supplier_phone',
                'suppliers.email as supplier_email',
                DB::raw("COUNT(DISTINCT purchase_orders.id) as total_po"),
                DB::raw("COUNT(DISTINCT CASE WHEN purchase_orders.status = 'draft' THEN purchase_orders.id END) as po_draft"),
                DB::raw("COUNT(DISTINCT CASE WHEN purchase_orders.status = 'sent' THEN purchase_orders.id END) as po_sent"),
                DB::raw("COUNT(DISTINCT CASE WHEN purchase_orders.status = 'received' THEN purchase_orders.id END) as po_received"),
                DB::raw("COUNT(DISTINCT CASE WHEN purchase_orders.status = 'closed' THEN purchase_orders.id END) as po_closed"),
                DB::raw("COALESCE(SUM(purchase_order_items.subtotal), 0) as total_pembelian"),
                DB::raw("COALESCE(SUM(purchase_order_items.quantity), 0) as total_quantity")
            );

        // Filter by date range if provided
        if ($start && $end) {
            $query->whereBetween('purchase_orders.created_at', [$start, $end]);
        }

        // Filter by specific supplier
        if ($supplierId) {
            $query->where('suppliers.id', $supplierId);
        }

        // Filter by PO status
        if ($status) {
            $query->where('purchase_orders.status', $status);
        }

        // Only show active suppliers
        $query->where('suppliers.is_active', true);

        $data = $query->groupBy(
            'suppliers.id',
            'suppliers.nama',
            'suppliers.alamat',
            'suppliers.telepon',
            'suppliers.email'
        )->get();

        $data->transform(function ($row) {
            $row->total_po = (int) ($row->total_po ?? 0);
            $row->po_draft = (int) ($row->po_draft ?? 0);
            $row->po_sent = (int) ($row->po_sent ?? 0);
            $row->po_received = (int) ($row->po_received ?? 0);
            $row->po_closed = (int) ($row->po_closed ?? 0);
            $row->total_pembelian = (float) ($row->total_pembelian ?? 0);
            $row->total_quantity = (float) ($row->total_quantity ?? 0);
            return $row;
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'meta' => [
                'start_date' => $start,
                'end_date' => $end,
                'total_suppliers' => $data->count(),
                'grand_total' => $data->sum('total_pembelian'),
            ],
        ]);
    }

    /**
     * Detail Pembelian per Supplier
     * Menampilkan daftar PO dan item yang dibeli dari supplier tertentu
     */
    public function detail(Request $request, $supplierId): JsonResponse
    {
        $start = $request->start_date;
        $end = $request->end_date;

        $query = DB::table('purchase_orders')
            ->join('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
            ->leftJoin('purchase_order_items', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->leftJoin('raw_materials', 'purchase_order_items.raw_material_id', '=', 'raw_materials.id')
            ->leftJoin('products', 'purchase_order_items.product_id', '=', 'products.id')
            ->leftJoin('units', function($join) {
                $join->on('purchase_order_items.unit_id', '=', 'units.id');
            })
            ->select(
                'purchase_orders.id as po_id',
                'purchase_orders.kode as po_number',
                'purchase_orders.order_date',
                'purchase_orders.status as po_status',
                'purchase_order_items.id as item_id',
                DB::raw("COALESCE(raw_materials.name, products.name) as item_name"),
                DB::raw("COALESCE(raw_materials.code, products.kode) as item_code"),
                'purchase_order_items.quantity',
                'units.name as unit_name',
                'purchase_order_items.price',
                'purchase_order_items.subtotal',
                'suppliers.nama as supplier_name'
            )
            ->where('purchase_orders.supplier_id', $supplierId);

        // Filter by date range
        if ($start && $end) {
            $query->whereBetween('purchase_orders.order_date', [$start, $end]);
        }

        $data = $query->orderBy('purchase_orders.order_date', 'desc')->get();

        // Group by PO
        $groupedData = $data->groupBy('po_id')->map(function ($items, $poId) {
            $firstItem = $items->first();
            return [
                'po_id' => $poId,
                'po_number' => $firstItem->po_number,
                'order_date' => $firstItem->order_date,
                'po_status' => $firstItem->po_status,
                'supplier_name' => $firstItem->supplier_name,
                'items' => $items->map(function ($item) {
                    return [
                        'item_id' => $item->item_id,
                        'item_code' => $item->item_code,
                        'item_name' => $item->item_name,
                        'quantity' => (float) $item->quantity,
                        'unit_name' => $item->unit_name,
                        'price' => (float) ($item->price ?? 0),
                        'subtotal' => (float) ($item->subtotal ?? 0),
                    ];
                })->values(),
                'total_items' => $items->count(),
                'total_amount' => (float) $items->sum('subtotal'),
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'data' => $groupedData,
            'meta' => [
                'start_date' => $start,
                'end_date' => $end,
                'supplier_id' => $supplierId,
                'total_po' => $groupedData->count(),
                'grand_total' => $groupedData->sum('total_amount'),
            ],
        ]);
    }

    /**
     * Top Items per Supplier
     * Menampilkan item yang paling banyak dibeli dari supplier
     */
    public function topItems(Request $request, $supplierId): JsonResponse
    {
        $start = $request->start_date;
        $end = $request->end_date;
        $limit = $request->limit ?? 10;

        $query = DB::table('purchase_order_items')
            ->join('purchase_orders', 'purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
            ->leftJoin('raw_materials', 'purchase_order_items.raw_material_id', '=', 'raw_materials.id')
            ->leftJoin('products', 'purchase_order_items.product_id', '=', 'products.id')
            ->leftJoin('units', 'purchase_order_items.unit_id', '=', 'units.id')
            ->select(
                DB::raw("COALESCE(raw_materials.code, products.kode) as item_code"),
                DB::raw("COALESCE(raw_materials.name, products.name) as item_name"),
                DB::raw("COALESCE(raw_materials.category, products.tipe) as category"),
                'units.name as unit_name',
                DB::raw("SUM(purchase_order_items.quantity) as total_quantity"),
                DB::raw("AVG(purchase_order_items.price) as avg_price"),
                DB::raw("SUM(purchase_order_items.subtotal) as total_spent"),
                DB::raw("COUNT(DISTINCT purchase_orders.id) as total_po")
            )
            ->where('purchase_orders.supplier_id', $supplierId);

        if ($start && $end) {
            $query->whereBetween('purchase_orders.order_date', [$start, $end]);
        }

        $data = $query
            ->groupBy(
                DB::raw("COALESCE(raw_materials.code, products.kode)"),
                DB::raw("COALESCE(raw_materials.name, products.name)"),
                DB::raw("COALESCE(raw_materials.category, products.tipe)"),
                'units.name'
            )
            ->orderBy('total_spent', 'desc')
            ->limit($limit)
            ->get();

        $data->transform(function ($row) {
            $row->total_quantity = (float) $row->total_quantity;
            $row->avg_price = (float) ($row->avg_price ?? 0);
            $row->total_spent = (float) ($row->total_spent ?? 0);
            $row->total_po = (int) $row->total_po;
            return $row;
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'meta' => [
                'start_date' => $start,
                'end_date' => $end,
                'supplier_id' => $supplierId,
                'limit' => $limit,
            ],
        ]);
    }

    /**
     * Supplier Performance Summary
     * Ringkasan performa supplier (on-time delivery, completion rate, dll)
     */
    public function performance(Request $request): JsonResponse
    {
        $start = $request->start_date;
        $end = $request->end_date;

        $query = DB::table('suppliers')
            ->leftJoin('purchase_orders', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->select(
                'suppliers.id as supplier_id',
                'suppliers.nama as supplier_name',
                DB::raw("COUNT(DISTINCT purchase_orders.id) as total_orders"),
                DB::raw("COUNT(DISTINCT CASE 
                    WHEN purchase_orders.status = 'received' OR purchase_orders.status = 'closed' 
                    THEN purchase_orders.id 
                END) as completed_orders"),
                DB::raw("COUNT(DISTINCT CASE 
                    WHEN purchase_orders.status = 'draft' 
                    THEN purchase_orders.id 
                END) as pending_orders"),
                DB::raw("ROUND(
                    COUNT(DISTINCT CASE 
                        WHEN purchase_orders.status = 'received' OR purchase_orders.status = 'closed' 
                        THEN purchase_orders.id 
                    END) * 100.0 / NULLIF(COUNT(DISTINCT purchase_orders.id), 0), 
                2) as completion_rate")
            )
            ->where('suppliers.is_active', true);

        if ($start && $end) {
            $query->whereBetween('purchase_orders.created_at', [$start, $end]);
        }

        $data = $query
            ->groupBy('suppliers.id', 'suppliers.nama')
            ->orderBy('total_orders', 'desc')
            ->get();

        $data->transform(function ($row) {
            $row->total_orders = (int) ($row->total_orders ?? 0);
            $row->completed_orders = (int) ($row->completed_orders ?? 0);
            $row->pending_orders = (int) ($row->pending_orders ?? 0);
            $row->completion_rate = (float) ($row->completion_rate ?? 0);
            return $row;
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'meta' => [
                'start_date' => $start,
                'end_date' => $end,
                'total_suppliers' => $data->count(),
            ],
        ]);
    }

    /**
     * Monthly Purchase Trend per Supplier
     * Tren pembelian bulanan dari supplier tertentu
     */
    public function monthlyTrend(Request $request, $supplierId): JsonResponse
    {
        $start = $request->start_date;
        $end = $request->end_date;

        $query = DB::table('purchase_orders')
            ->join('purchase_order_items', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->select(
                DB::raw("DATE_FORMAT(purchase_orders.order_date, '%Y-%m') as month"),
                DB::raw("COUNT(DISTINCT purchase_orders.id) as total_po"),
                DB::raw("SUM(purchase_order_items.quantity) as total_quantity"),
                DB::raw("SUM(purchase_order_items.subtotal) as total_amount")
            )
            ->where('purchase_orders.supplier_id', $supplierId);

        if ($start && $end) {
            $query->whereBetween('purchase_orders.order_date', [$start, $end]);
        }

        $data = $query
            ->groupBy(DB::raw("DATE_FORMAT(purchase_orders.order_date, '%Y-%m')"))
            ->orderBy('month', 'asc')
            ->get();

        $data->transform(function ($row) {
            $row->total_po = (int) $row->total_po;
            $row->total_quantity = (float) $row->total_quantity;
            $row->total_amount = (float) ($row->total_amount ?? 0);
            return $row;
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'meta' => [
                'start_date' => $start,
                'end_date' => $end,
                'supplier_id' => $supplierId,
                'total_months' => $data->count(),
                'grand_total' => $data->sum('total_amount'),
            ],
        ]);
    }
}