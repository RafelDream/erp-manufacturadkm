<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\search;

class InventoryReportController extends Controller
{
    /**
     * Laporan Kartu Persediaan Product
     */
    public function product(Request $request): JsonResponse
    {
        $start    = $request->start_date;
        $end      = $request->end_date;
        $search   = $request->search;

        $query = DB::table('products')
            ->leftJoin('stock_movements', 'products.id', '=', 'stock_movements.product_id')
            ->leftJoin('units', 'products.unit_id', '=', 'units.id')
            ->select(
                'products.kode as kode',
                'products.name as nama',
                'products.tipe as kategori',
                'units.name as satuan',
                DB::raw("SUM(
                    CASE 
                        WHEN stock_movements.created_at < ? 
                        THEN stock_movements.quantity * (CASE WHEN stock_movements.type IN ('in', 'adjustment')  THEN 1 ELSE -1 END)
                        ELSE 0 
                    END
                ) as stok_awal"),
                DB::raw("SUM(
                    CASE 
                        WHEN stock_movements.type = 'in' 
                        AND stock_movements.created_at BETWEEN ? AND ? 
                        THEN stock_movements.quantity 
                        ELSE 0 
                    END
                ) as stok_masuk"),
                DB::raw("SUM(
                    CASE 
                        WHEN stock_movements.type = 'out' 
                        AND stock_movements.created_at BETWEEN ? AND ? 
                        THEN stock_movements.quantity 
                        ELSE 0 
                    END
                ) as stok_keluar"),
                DB::raw("SUM(
                    CASE
                        WHEN stock_movements.type = 'adjustment'
                        AND stock_movements.created_at BETWEEN ? AND ?
                        THEN stock_movements.quantity
                        ELSE 0
                    END
                ) as stock_adjustment")
            )
            ->setBindings([$start, $start, $end, $start, $end, $start, $end]);

        // Filter Kategori
        //if ($category) {
            //$query->where('products.category_id', $category);//
       // }//

        // Filter Pencarian
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('products.name', 'like', "%{$search}%")
                    ->orWhere('products.kode', 'like', "%{$search}%");
            });
        }

        $data = $query->groupBy('products.id', 'products.kode', 'products.name', 'products.tipe', 'units.name')->get();

        $data->transform(function ($row) {
            $row->stok_awal   = (float) ($row->stok_awal ?? 0);
            $row->stok_masuk  = (float) ($row->stok_masuk ?? 0);
            $row->stok_keluar = (float) ($row->stok_keluar ?? 0);
            $row->stock_adj = (float) ($row->stock_adjustment ?? 0);
            $row->stok_akhir  = $row->stok_awal + $row->stok_masuk - $row->stok_keluar + $row->stock_adj;
            return $row;
        });

        return response()->json([
            'status' => 'success',
            'data'   => $data,
            'meta'   => [
                'start_date' => $start,
                'end_date'   => $end,
            ],
        ]);
    }

    /**
     * Laporan Kartu Persediaan Raw Material
     */
    public function rawMaterial(Request $request): JsonResponse
    {
        $start = $request->start_date;
        $end   = $request->end_date;
        $search = $request->search;

        $query = DB::table('raw_materials')
            ->leftJoin('raw_material_stock_movements', 'raw_materials.id', '=', 'raw_material_stock_movements.raw_material_id')
            ->select(
                'raw_materials.code as kode_produk',
                'raw_materials.name as nama_produk',
                'raw_materials.category as kategori',
                'raw_materials.unit as satuan',
                DB::raw("SUM(
                    CASE 
                        WHEN raw_material_stock_movements.created_at < ? 
                        THEN raw_material_stock_movements.quantity * (CASE WHEN raw_material_stock_movements.movement_type IN ('IN', 'ADJUSTMENT', 'TRANSFER_IN') THEN 1 ELSE -1 END)
                        ELSE 0 
                    END
                ) as stok_awal"),
                DB::raw("SUM(
                    CASE 
                        WHEN raw_material_stock_movements.movement_type IN ('IN', 'TRANSFER_IN')
                        AND raw_material_stock_movements.created_at BETWEEN ? AND ? 
                        THEN raw_material_stock_movements.quantity 
                        ELSE 0 
                    END
                ) as stok_masuk"),
                DB::raw("SUM(
                    CASE 
                        WHEN raw_material_stock_movements.movement_type IN ('OUT', 'TRANSFER_OUT') 
                        AND raw_material_stock_movements.created_at BETWEEN ? AND ? 
                        THEN raw_material_stock_movements.quantity 
                        ELSE 0 
                    END
                ) as stok_keluar")
            )
            ->setBindings([$start, $start, $end, $start, $end]);   

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('raw_materials.name', 'like', "%{search}%")
                  ->orWhere('raw_materials.code', 'like', "%{search}%");
            });
        }

        $data = $query->groupBy('raw_materials.id', 'raw_materials.code', 'raw_materials.name', 'raw_materials.category', 'raw_materials.unit')->get();

        $data->transform(function ($row) {
            $row->stok_awal   = (float) ($row->stok_awal ?? 0);
            $row->stok_masuk  = (float) ($row->stok_masuk ?? 0);
            $row->stok_keluar = (float) ($row->stok_keluar ?? 0);
            $row->stok_akhir  = $row->stok_awal + $row->stok_masuk - $row->stok_keluar;
            return $row;
        });

        return response()->json([
            'status' => 'success',
            'data'   => $data,
            'meta' => [
                'start_date' => $start,
                'end_date' => $end,
            ],  
        ]);
    }

    /**
     * Laporan Detail Barang Masuk
     */
    public function incomingGoodsLog(Request $request): JsonResponse
    {
        $start  = $request->start_date;
        $end    = $request->end_date;
        $search = $request->search;

        // 1. Query Barang Masuk dari Produk
        $productIncoming = DB::table('stock_movements')
            ->join('products', 'stock_movements.product_id', '=', 'products.id')
            ->select(
                'stock_movements.reference_id as no_dokumen', // No Dokumen
                'stock_movements.created_at as tanggal',      // Tanggal
                'products.kode as kode_barang',               // Kode Barang
                'products.name as nama_barang',               // Nama Barang
                'stock_movements.quantity as qty',            // Qty
                DB::raw("'Product' as tipe_barang")
            )
            ->where('stock_movements.type', 'in');

        // 2. Query Barang Masuk dari Bahan Baku
        $rawIncoming = DB::table('raw_material_stock_movements')
            ->join('raw_materials', 'raw_material_stock_movements.raw_material_id', '=', 'raw_materials.id')
            ->select(
                'raw_material_stock_movements.reference_id as no_dokumen',
                'raw_material_stock_movements.created_at as tanggal',
                'raw_materials.code as kode_barang',
                'raw_materials.name as nama_barang',
                'raw_material_stock_movements.quantity as qty',
                DB::raw("'RawMaterial' as tipe_barang")
            )
            ->where('raw_material_stock_movements.movement_type', 'IN');

        // Gabungkan menggunakan UNION
        $query = DB::table(DB::raw("({$productIncoming->toSql()}) as combined"))
            ->mergeBindings($productIncoming)
            ->union($rawIncoming);

        // Filter Tanggal jika ada 
        if ($start && $end) {
            $query->whereBetween('tanggal', [$start . ' 00:00:00', $end . ' 23:59:59']);
        }

        // Filter Pencarian (Nama Barang atau Kode)
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('nama_barang', 'like', "%{$search}%")
                  ->orWhere('kode_barang', 'like', "%{$search}%");
            });
        }

        $data = $query->orderBy('tanggal', 'desc')->get();

        // Transformasi data untuk memastikan tipe data numerik
        $groupedData = $data->groupBy(function($item) {
            return \Carbon\Carbon::parse($item->tanggal)->format('Y-m-d');
        })->map(function($dateGroup) {
            return $dateGroup->groupBy('tipe_barang')->map(function($categoryGroup) {
                return [
                    'items' => $categoryGroup->values(), // values() untuk mereset index array agar rapi di JSON
                    'sub_total_qty' => (float) $categoryGroup->sum('qty')
                ];
            });
        });

        return response()->json([
            'status' => 'success',
            'data'   => $groupedData,
            'meta'   => [
                'start_date' => $request->start_date,
                'end_date'   => $request->end_date,
                'grand_total_qty' => (float) $data->sum('qty'), // Untuk baris "Total"
            ],
        ]);
    }

    /**
     * Laporan Detail Barang Keluar 
     */
    public function outgoingGoodsLog(Request $request): JsonResponse
    {
        $start  = $request->start_date;
        $end    = $request->end_date;
        $search = $request->search;

        // 1. Query Barang Keluar dari Produk
        $productOutgoing = DB::table('stock_movements')
            ->join('products', 'stock_movements.product_id', '=', 'products.id')
            ->leftJoin('users', 'stock_movements.created_by', '=', 'users.id')
            ->select(
                'stock_movements.reference_id as no_dokumen',
                'stock_movements.created_at as tanggal',
                'users.name as nama_pengambil',               // Mengambil nama user penginput
                'products.kode as kode_barang',
                'products.name as nama_barang',
                'stock_movements.quantity as qty',
                DB::raw("'Product' as tipe_barang")
            )
            ->where('stock_movements.type', 'out');

        // 2. Query Barang Keluar dari Bahan Baku
        $rawOutgoing = DB::table('raw_material_stock_movements')
            ->join('raw_materials', 'raw_material_stock_movements.raw_material_id', '=', 'raw_materials.id')
            ->leftJoin('users', 'raw_material_stock_movements.created_by', '=', 'users.id')
            ->select(
                'raw_material_stock_movements.reference_id as no_dokumen',
                'raw_material_stock_movements.created_at as tanggal',
                'users.name as nama_pengambil',
                'raw_materials.code as kode_barang',
                'raw_materials.name as nama_barang',
                'raw_material_stock_movements.quantity as qty',
                DB::raw("'RawMaterial' as tipe_barang")
            )
            ->whereIn('raw_material_stock_movements.movement_type', ['OUT', 'TRANSFER_OUT']);

        // Gabungkan menggunakan UNION
        $query = DB::table(DB::raw("({$productOutgoing->toSql()}) as combined"))
            ->mergeBindings($productOutgoing)
            ->union($rawOutgoing);

        // Filter Tanggal
        if ($start && $end) {
            $query->whereBetween('tanggal', [$start . ' 00:00:00', $end . ' 23:59:59']);
        }

        // Filter Pencarian
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('nama_barang', 'like', "%{$search}%")
                  ->orWhere('kode_barang', 'like', "%{$search}%")
                  ->orWhere('no_dokumen', 'like', "%{$search}%");
            });
        }

        $data = $query->orderBy('tanggal', 'desc')->get();

        // Pastikan qty dalam format numerik
        $groupedData = $data->groupBy(function($item) {
            return \Carbon\Carbon::parse($item->tanggal)->format('Y-m-d');
        })->map(function($dateGroup) {
            return $dateGroup->groupBy('tipe_barang')->map(function($categoryGroup) {
                return [
                    'items' => $categoryGroup->values(),
                    'sub_total_qty' => (float) $categoryGroup->sum('qty')
                ];
            });
        });

        return response()->json([
            'status' => 'success',
            'data'   => $groupedData,
            'meta'   => [
                'start_date' => $request->start_date,
                'end_date'   => $request->end_date,
                'grand_total_qty' => (float) $data->sum('qty'),
            ],
        ]);
    }
}