<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryAssignment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'no_spkp', 'work_order_id', 'driver_name', 
        'vehicle_plate_number', 'tanggal_kirim', 'status', 'notes', 'created_by'
    ];

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }
}
