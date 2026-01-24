<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StockAdjustmentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_adjustment_id',
        'product_id',
        'system_qty',
        'actual_qty',
        'difference',        
    ];

    /**
     * Relasi ke Header Adjustment
     */

    public function adjustment()
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
    }

    /**
     * Relasi ke Product
     */

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Logika Otomatisasi Perhitungan Selisih
     */
    protected static function booted()
    {
        static::saving(function ($item) {
            // Memastikan selisih selalu akurat: Aktual - Sistem
            $item->difference = $item->actual_qty - $item->system_qty;
        });
    }
}
