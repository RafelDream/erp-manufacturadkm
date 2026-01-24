<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockRequestItem extends Model
{
    protected $table = 'stock_requests_items';

    protected $fillable = [
        'stock_request_id',
        'product_id',
        'quantity',
    ];

     // Header permintaan
    public function stockRequest()
    {
        return $this->belongsTo(StockRequest::class);
    }

    // Produk yang diminta
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
