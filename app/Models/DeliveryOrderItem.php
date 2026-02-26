<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_order_id',
        'product_id',
        'qty_realisasi'
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
}
