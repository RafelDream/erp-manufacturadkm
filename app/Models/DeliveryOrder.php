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
        'tanggal',
        'no_spk',
        'sales_order_id',
        'customer_id',
        'warehouse_id',
        'expedition',
        'vehicle_number',
        'status',
        'notes',
        'created_by'
    ];

    protected $casts = [
        'tanggal' => 'date',
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

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function isShipped()
    {
        return $this->status === 'shipped';
    }

    /**
     * Cek apakah status sudah diterima customer
     */
    public function isReceived()
    {
        return $this->status === 'received';
    }
}
