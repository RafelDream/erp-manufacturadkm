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
        'qty_shipped',
        'price',        
        'subtotal' 
    ];

    // 🔥 Casting agar aman untuk perhitungan
    protected $casts = [
        'qty_pesanan' => 'decimal:2',
        'price'       => 'decimal:2',
        'subtotal'    => 'decimal:2',
    ];

    protected $appends = ['qty_remaining'];

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

    public function getQtyRemainingAttribute() {
    return max(0, $this->qty_pesanan - $this->qty_shipped);
    }
}
