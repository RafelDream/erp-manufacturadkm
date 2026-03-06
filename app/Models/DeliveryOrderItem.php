<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_order_id',
        'sales_order_item_id',
        'product_id',
        'qty_realisasi'
    ];

    protected $casts = [
        'qty_realisasi' => 'double',
    ];
    
    /**
     * Relasi balik ke Header Surat Jalan
     */
    public function deliveryOrder()
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    /**
     * Relasi ke Master Produk (untuk ambil nama produk/satuan)
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function salesOrderItem() 
    { 
        return $this->belongsTo(SalesOrderItem::class); 
    }
}
