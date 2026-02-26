<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SalesReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_return_id',
        'product_id',
        'qty',
        'condition',
        'price',
        'subtotal'
    ];

    /**
     * Relasi Balik ke Header Retur
     */
    public function salesReturn()
    {
        return $this->belongsTo(SalesReturn::class);
    }

    /**
     * Relasi ke Produk (Untuk ambil nama produk, satuan, dll)
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
