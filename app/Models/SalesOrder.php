<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'no_spk',
        'tanggal',
        'customer_id',
        'sales_quotation_id', // 🔥 tambahan
        'total_price',        // 🔥 tambahan
        'notes',
        'status',
        'created_by'
    ];

    // 🔥 Casting biar aman
    protected $casts = [
        'tanggal'     => 'date',
        'total_price' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(SalesOrderItem::class, 'sales_order_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // 🔥 Relasi ke quotation asal
    public function salesQuotation()
    {
        return $this->belongsTo(SalesQuotation::class);
    }

    public function deliveryOrders()
    {
        // Relasi ke Surat Jalan melalui kolom no_spk
        return $this->hasMany(DeliveryOrder::class, 'no_spk', 'sales_order_id');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER METHODS
    |--------------------------------------------------------------------------
    */

    public function isApproved()
    {
        return $this->status === 'approved';
    }

    public function isInProgress()
    {
        return $this->status === 'in_progress';
    }

    public function isCompleted()
    {
        return $this->status === 'completed';
    }
}
