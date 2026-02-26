<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesReturn extends Model
{
    use SoftDeletes; 

    protected $fillable = ['return_no', 'sales_invoice_id', 'customer_id', 'return_date', 'total_return_amount', 'reason', 'created_by'];

    public function items() {
        return $this->hasMany(SalesReturnItem::class);
    }

    public function invoice() {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }
}
