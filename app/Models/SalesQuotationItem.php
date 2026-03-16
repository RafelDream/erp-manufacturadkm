<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SalesQuotationItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_quotation_id',
        'product_id',
        'qty',
        'price',
        'subtotal'
    ];

    //  Casting agar aman untuk perhitungan
    protected $casts = [
        'qty'      => 'decimal:2',
        'price'    => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function salesQuotation()
    {
        return $this->belongsTo(SalesQuotation::class);
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

    //  Optional: hitung otomatis jika tidak di-set
    public function calculateSubtotal()
    {
        return $this->qty * $this->price;
    }
}
