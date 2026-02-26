<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkOrder extends Model
{
    use SoftDeletes;

    protected $fillable = ['no_wo', 'sales_order_id', 'tanggal', 'status', 'notes', 'created_by'];

    public function salesOrder() {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function items() {
        return $this->hasMany(WorkOrderItem::class);
    }

    public function creator() {
        return $this->belongsTo(User::class, 'created_by');
    }
}
