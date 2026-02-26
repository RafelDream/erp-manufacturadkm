<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SalesOrderItem extends Model
{
    use HasFactory;

    protected $table = 'sales_order_items';

    protected $fillable = [
        'sales_order_id',
        'product_id',
        'qty_pesanan',
        'price',        // 🔥 tambahan
        'subtotal'      // 🔥 tambahan
    ];

    // 🔥 Casting agar aman untuk perhitungan
    protected $casts = [
        'qty_pesanan' => 'decimal:2',
        'price'       => 'decimal:2',
        'subtotal'    => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER
    |--------------------------------------------------------------------------
    */

    public function calculateSubtotal()
    {
        return $this->qty_pesanan * $this->price;
    }
}
