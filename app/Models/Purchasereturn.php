<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReturn extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'return_number',
        'return_date',
        'purchase_order_id',
        'goods_receipt_id',
        'warehouse_id',
        'delivery_note_number',
        'vehicle_number',
        'reason',
        'status',
        'notes',
        'created_by',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'realized_by',
        'realized_at',
        'completed_by',
        'completed_at',
    ];

    protected $casts = [
        'return_date' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'realized_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Relasi ke Purchase Order
     */
    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * Relasi ke Goods Receipt
     */
    public function goodsReceipt()
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    /**
     * Relasi ke Warehouse
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Relasi ke Items
     */
    public function items()
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }

    /**
     * Relasi ke Creator
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relasi ke Submitter
     */
    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * Relasi ke Approver
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Relasi ke Realizer
     */
    public function realizer()
    {
        return $this->belongsTo(User::class, 'realized_by');
    }

    /**
     * Relasi ke Completer
     */
    public function completer()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * Alias untuk poster (compatibility)
     */
    public function poster()
    {
        return $this->realizer();
    }
}