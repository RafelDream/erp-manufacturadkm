<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoodsReceipt extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'receipt_number',
        'receipt_date',
        'purchase_order_id',
        'warehouse_id',
        'delivery_note_number',
        'vehicle_number',
        'po_reference',
        'type',
        'status',
        'notes',
        'created_by',
        'posted_by',
        'posted_at',
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'posted_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function posted()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}