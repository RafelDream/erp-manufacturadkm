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
        return $this->hasMany(DeliveryOrder::class, 'sales_order_id');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER METHODS
    |--------------------------------------------------------------------------
    */

   public function getDeliveryProgressAttribute()
    {
        $totalOrdered = $this->items->sum('qty_pesanan');
        $totalShipped = $this->items->sum('qty_shipped');
        
        if ($totalOrdered <= 0) return 0;
        
        return round(($totalShipped / $totalOrdered) * 100, 2);
    }

    // Memastikan status sinkron dengan realita pengiriman di gudang
    public function syncStatus()
    {
        $totalOrdered = $this->items->sum('qty_pesanan');
        $totalShipped = $this->items->sum('qty_shipped');

        // Jika belum ada pengiriman sama sekali, jangan ubah status (tetap pending/approved)
        if ($totalShipped <= 0) return; 

        if ($totalShipped >= $totalOrdered) {
            $this->status = 'completed';
        } else {
            $this->status = 'partial';
        }

        $this->save();
    }

    // Status Checkers untuk UI Next.js
    public function isPending() { return $this->status === 'pending'; }
    public function isApproved() { return $this->status === 'approved'; }
    public function isPartial() { return $this->status === 'partial'; }
    public function isCompleted() { return $this->status === 'completed'; }

    // Menambahkan delivery_progress ke output JSON secara otomatis
    protected $appends = ['delivery_progress'];
}