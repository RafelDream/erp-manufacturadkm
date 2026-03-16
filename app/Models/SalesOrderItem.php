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

    //  Casting agar aman untuk perhitungan
    protected $casts = [
        'qty_pesanan' => 'float',
        'qty_shipped' => 'float',
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
        return (float) $this->qty_pesanan * (float) $this->price;
    }

    public function getQtyRemainingAttribute() 
    {
        $remaining = $this->qty_pesanan - $this->qty_shipped;

        return max(0, $remaining);
    }
}
