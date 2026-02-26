<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesInvoiceItem extends Model
{
    protected $fillable = ['sales_invoice_id', 'product_id', 'qty', 'price', 'subtotal'];
    public function product() { return $this->belongsTo(Product::class); }
}
