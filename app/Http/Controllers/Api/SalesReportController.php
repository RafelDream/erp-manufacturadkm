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
        $paymentStatus = $request->payment_status;

        $query = DB::table('sales_orders')
            ->join('customers', 'sales_orders.customer_id', '=', 'customers.id')
            ->join('sales_invoices', 'sales_orders.id', '=', 'sales_invoices.sales_order_id')
            ->select(
                'customers.name as customer_name',
                DB::raw('COUNT(sales_orders.id) as total_orders'),
                DB::raw('SUM(sales_orders.total_price) as total_kontribusi'),
                DB::raw('SUM(sales_invoices.balance_due) as total_piutang')
            )
            ->whereBetween('sales_orders.tanggal', [$startDate, $endDate])
            ->whereNull('sales_orders.deleted_at')
            ->groupBy('customers.id', 'customers.name')
            ->orderBy('total_kontribusi', 'DESC')
            ->where('sales_orders.status', '=', 'completed');


            if ($paymentStatus == 'paid') {
            $query->where('sales_invoices.balance_due', '<=', 0);
            } elseif ($paymentStatus == 'unpaid') {
            $query->where('sales_invoices.balance_due', '>', 0);
            }

            $data = $query->groupBy('customers.id', 'customers.name')
            ->orderBy('total_kontribusi', 'DESC')
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
            ->where('sales_orders.status', '=', 'completed')
            ->whereNull('sales_orders.deleted_at')
            ->groupBy('products.id', 'products.name', 'products.kode');

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
        $paymentStatus = $request->payment_status;

        $query = DB::table('sales_orders')
            ->join('customers', 'sales_orders.customer_id', '=', 'customers.id')
            ->join('sales_invoices', 'sales_orders.id', '=', 'sales_invoices.sales_order_id')
            ->select(
            'customers.name as customer_name',
            DB::raw('COUNT(sales_orders.id) as total_orders'),
            DB::raw('SUM(sales_orders.total_price) as total_kontribusi'),
            DB::raw('SUM(sales_invoices.balance_due) as total_piutang')
        )
        ->whereBetween('sales_orders.tanggal', [$startDate, $endDate])
        ->where('sales_orders.status', '=', 'completed')
        ->whereNull('sales_orders.deleted_at')
        ->groupBy('customers.id', 'customers.name');

        if ($paymentStatus == 'paid') {
            $query->where('sales_invoices.balance_due', '<=', 0);
        } elseif ($paymentStatus == 'unpaid') {
            $query->where('sales_invoices.balance_due', '>', 0);
        }

        $data = $query->groupBy('customers.id', 'customers.name')
            ->orderBy('total_kontribusi', 'DESC');

        $pdf = Pdf::loadView('salesreport.report_customer', compact('data', 'startDate', 'endDate'));
        return $pdf->stream('laporan-penjualan-customer.pdf');
    }

    /**
     * 4. Laporan Aging Piutang (Analisis Umur Piutang)
     */
    public function agingReport(Request $request)
    {
        // Mengambil semua invoice yang belum lunas
        $data = DB::table('sales_invoices')
            ->join('customers', 'sales_invoices.customer_id', '=', 'customers.id')
            ->select(
                'customers.name as customer_name',
                'sales_invoices.no_invoice',
                'sales_invoices.tanggal',
                'sales_invoices.due_date',
                'sales_invoices.balance_due',
                DB::raw('DATEDIFF(NOW(), sales_invoices.due_date) as days_overdue')
            )
            ->where('sales_invoices.balance_due', '>', 0)
            ->whereNull('sales_invoices.deleted_at')
            ->map(function ($item) {
                // Pengelompokan umur piutang
                $days = $item->days_overdue;
                $item->status_aging = 'Belum Jatuh Tempo';
                
                if ($days > 0 && $days <= 30) {
                    $item->status_aging = '1 - 30 Hari';
                } elseif ($days > 30 && $days <= 60) {
                    $item->status_aging = '31 - 60 Hari';
                } elseif ($days > 60 && $days <= 90) {
                    $item->status_aging = '61 - 90 Hari';
                } elseif ($days > 90) {
                    $item->status_aging = '> 90 Hari (Macet)';
                }

                return $item;
            });

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Cetak PDF Laporan Aging Piutang
     */
    public function agingReportPdf(Request $request)
    {
        $data = DB::table('sales_invoices')
            ->join('customers', 'sales_invoices.customer_id', '=', 'customers.id')
            ->select(
                'customers.name as customer_name',
                'sales_invoices.no_invoice',
                'sales_invoices.tanggal',
                'sales_invoices.due_date',
                'sales_invoices.balance_due',
                DB::raw('DATEDIFF(NOW(), sales_invoices.due_date) as days_overdue')
            )
            ->where('sales_invoices.balance_due', '>', 0)
            ->whereNull('sales_invoices.deleted_at');

        $today = date('d M Y');
        $pdf = Pdf::loadView('salesreport.report_aging', compact('data', 'today'));
        return $pdf->stream('laporan-aging-piutang.pdf');
    }
}
