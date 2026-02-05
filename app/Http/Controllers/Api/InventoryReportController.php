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
}