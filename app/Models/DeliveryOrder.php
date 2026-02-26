<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class DeliveryOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'no_sj',
        'delivery_assignment_id',
        'tanggal',
        'no_spk',
        'customer_id',
        'warehouse_id',
        'expedition',
        'vehicle_number',
        'status',
        'notes',
        'created_by'
    ];

    /**
     * Relasi ke detail item (Satu SJ memiliki banyak barang)
     */
    public function items()
    {
        return $this->hasMany(DeliveryOrderItem::class);
    }

    /**
     * Relasi ke Master Customer yang baru kita buat
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Relasi ke Gudang Pengirim
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Relasi ke User yang membuat dokumen
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignment()
    {
    return $this->belongsTo(DeliveryAssignment::class, 'delivery_assignment_id');
    }
}
