<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkOrderItem extends Model
{
    protected $fillable = ['work_order_id', 'product_id', 'qty_to_process'];

    public function product() {
        return $this->belongsTo(Product::class);
    }
}
