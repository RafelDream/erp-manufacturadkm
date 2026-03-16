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
        'sales_quotation_id', 
        'total_price',        
        'notes',
        'status',
        'created_by'
    ];

    
    protected $casts = [
        'tanggal'     => 'date',
        'total_price' => 'decimal:2',
    ];

    // Menambahkan delivery_progress ke output JSON secara otomatis
    protected $appends = ['delivery_progress'];

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

    
    public function salesQuotation()
    {
        return $this->belongsTo(SalesQuotation::class);
    }

    public function deliveryOrders()
    {
        // Relasi ke Delivery Order melalui sales_order_id
        return $this->hasMany(DeliveryOrder::class, 'sales_order_id');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER METHODS
    |--------------------------------------------------------------------------
    */

   public function getDeliveryProgressAttribute()
    {
        if ($this->relationLoaded('items')) {
        $items = $this->items;
        } else {
        $items = $this->items()->get();
        }

        $totalOrdered = (float) $items->sum('qty_pesanan');
        $totalShipped = (float) $items->sum('qty_shipped');

        if ($totalOrdered <= 0) {
        return 0;
        }

        return round(($totalShipped / $totalOrdered) * 100, 2);
        }

    // Memastikan status sinkron dengan realita pengiriman di gudang
    public function syncStatus()
    {
        $items = $this->items()->get();

        if ($items->isEmpty()) return;

        $totalShipped = $items->sum('qty_shipped');

        $allItemsFulfilled = $items->every(
            fn($item) => (float) $item->qty_shipped >= (float) $item->qty_pesanan
        );
 

        // Jika belum ada pengiriman sama sekali, jangan ubah status (tetap pending/approved)
        if ($totalShipped <= 0) {
             $newStatus = $this->status === 'approved' ? 'approved' : 'pending';
        } elseif ($allItemsFulfilled) {
            // Semua item di semua baris sudah terpenuhi → selesai
            $newStatus = 'completed';
        } else {
            // Ada pengiriman tapi belum semua terpenuhi → sebagian
            $newStatus = 'partial';
        }
        if ($this->status === 'cancelled' || $this->status === $newStatus) {
            return;
        }
        
        $this->status = $newStatus;
        $this->save();
    }

    // Status Checkers untuk UI Next.js
    public function isPending() { return $this->status === 'pending'; }
    public function isApproved() { return $this->status === 'approved'; }
    public function isPartial() { return $this->status === 'partial'; }
    public function isCompleted() { return $this->status === 'completed'; }
}