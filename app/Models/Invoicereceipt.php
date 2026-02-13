<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceReceipt extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'receipt_number',
        'purchase_order_id',
        'transaction_date',
        'requester_id',
        'supplier_id',
        'status',
        'notes',
        'created_by',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    /**
     * Relasi ke Purchase Order
     */
    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * Relasi ke Supplier
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Relasi ke Invoices (Faktur)
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Relasi ke Creator
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relasi ke Requester (Pemohon)
     */
    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
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
     * Get total amount dari semua invoices
     */
    public function getTotalAmountAttribute()
    {
        return $this->invoices->sum('amount');
    }

    /**
     * Get invoice count
     */
    public function getInvoiceCountAttribute()
    {
        return $this->invoices->count();
    }
}