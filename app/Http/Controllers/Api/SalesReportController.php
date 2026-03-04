<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalesOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class SalesReportController extends Controller
{
    /**
     * 1. Laporan Penjualan Per Barang (Product)
     */
    public function productReport(Request $request)
    {
        $startDate = $request->start_date ?? date('Y-m-01');
        $endDate = $request->end_date ?? date('Y-m-d');

        $data = DB::table('sales_order_items')
            ->join('sales_orders', 'sales_order_items.sales_order_id', '=', 'sales_orders.id')
            ->join('products', 'sales_order_items.product_id', '=', 'products.id')
            ->select(
                'products.name as product_name',
                'products.kode as product_code',
                DB::raw('SUM(sales_order_items.qty_pesanan) as total_qty'),
                DB::raw('SUM(sales_order_items.qty_pesanan * sales_order_items.price) as total_omzet')
            )
            ->whereBetween('sales_orders.tanggal', [$startDate, $endDate])
            ->whereNull('sales_orders.deleted_at')
            ->groupBy('products.id', 'products.name', 'products.kode')
            ->orderBy('total_qty', 'DESC')
            ->where('sales_orders.status', '=', 'completed')
            ->get();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * 2. Laporan Penjualan Per Customer
     */
    public function customerReport(Request $request)
    {
        $startDate = $request->start_date ?? date('Y-m-01');
        $endDate = $request->end_date ?? date('Y-m-d');

        $data = DB::table('sales_orders')
            ->join('customers', 'sales_orders.customer_id', '=', 'customers.id')
            ->select(
                'customers.name as customer_name',
                DB::raw('COUNT(sales_orders.id) as total_orders'),
                DB::raw('SUM(sales_orders.total_price) as total_kontribusi')
            )
            ->whereBetween('sales_orders.tanggal', [$startDate, $endDate])
            ->whereNull('sales_orders.deleted_at')
            ->groupBy('customers.id', 'customers.name')
            ->orderBy('total_kontribusi', 'DESC')
            ->where('sales_orders.status', '=', 'completed')
            ->get();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * 3. Resume Penjualan (Ringkasan Eksekutif)
     */
    public function salesResume(Request $request)
    {
        $startDate = $request->start_date ?? date('Y-m-01');
        $endDate = $request->end_date ?? date('Y-m-d');

        $resume = [
            'total_omzet' => SalesOrder::whereBetween('tanggal', [$startDate, $endDate])
                                    ->where('status', 'completed')
                                    ->sum('total_price'),
            'total_transaksi' => SalesOrder::whereBetween('tanggal', [$startDate, $endDate])
                                        ->where('status', 'completed')
                                        ->count(),
        // Tambahkan Top Customer
            'top_customer' => DB::table('sales_orders')
                            ->join('customers', 'sales_orders.customer_id', '=', 'customers.id')
                            ->select('customers.name', DB::raw('SUM(total_price) as total'))
                            ->whereBetween('tanggal', [$startDate, $endDate])
                            ->groupBy('customers.id', 'customers.name')
                            ->orderBy('total', 'desc')
                            ->where('sales_orders.status', '=', 'completed')
                            ->first()
        ];

        return response()->json(['success' => true, 'data' => $resume]);
    }

    public function monthlyTrend(Request $request)
    {
        $year = $request->year ?? date('Y');

        $data = DB::table('sales_orders')
        ->select(
            DB::raw('MONTH(tanggal) as bulan'),
            DB::raw('SUM(total_price) as total_omzet')
        )
        ->whereYear('tanggal', $year)
        ->where('status', 'completed')
        ->groupBy(DB::raw('MONTH(tanggal)'))
        ->orderBy('bulan', 'ASC')
        ->where('sales_orders.status', '=', 'completed')
        ->get();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
    * Cetak PDF Laporan Per Barang
    */
    public function productReportPdf(Request $request)
    {
        $startDate = $request->start_date ?? date('Y-m-01');
        $endDate = $request->end_date ?? date('Y-m-d');

        $data = DB::table('sales_order_items')
            ->join('sales_orders', 'sales_order_items.sales_order_id', '=', 'sales_orders.id')
            ->join('products', 'sales_order_items.product_id', '=', 'products.id')
            ->select(
                'products.name as product_name',
                'products.kode as product_code',
                DB::raw('SUM(sales_order_items.qty_pesanan) as total_qty'),
                DB::raw('SUM(sales_order_items.qty_pesanan * sales_order_items.price) as total_omzet')
            )
            ->whereBetween('sales_orders.tanggal', [$startDate, $endDate])
            ->whereNull('sales_orders.deleted_at')
            ->groupBy('products.id', 'products.name', 'products.kode')
            ->get();

        $pdf = Pdf::loadView('salesreport.report_product', compact('data', 'startDate', 'endDate'));
        return $pdf->stream('laporan-penjualan-produk.pdf');
    }

    /**
    * Cetak PDF Laporan Per Customer
    */
    public function customerReportPdf(Request $request)
    {
        $startDate = $request->start_date ?? date('Y-m-01');
        $endDate = $request->end_date ?? date('Y-m-d');

        $data = DB::table('sales_orders')
            ->join('customers', 'sales_orders.customer_id', '=', 'customers.id')
            ->select(
            'customers.name as customer_name',
            DB::raw('COUNT(sales_orders.id) as total_orders'),
            DB::raw('SUM(sales_orders.total_price) as total_kontribusi')
        )
        ->whereBetween('sales_orders.tanggal', [$startDate, $endDate])
        ->whereNull('sales_orders.deleted_at')
        ->groupBy('customers.id', 'customers.name')
        ->get();

        $pdf = Pdf::loadView('salesreport.report_customer', compact('data', 'startDate', 'endDate'));
        return $pdf->stream('laporan-penjualan-customer.pdf');
    }
}
