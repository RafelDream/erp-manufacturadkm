<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesInvoice extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'no_invoice', 
        'sales_order_id',
        'delivery_order_id', 
        'customer_id', 
        'tanggal', 
        'due_date', 
        'dp_amount', 
        'amount_paid',
        'balance_due',
        'gallon_loan_qty',
        'gallon_deposit_status',
        'discount_amount', 
        'final_amount',
        'payment_type',
        'total_price', 
        'status', 
        'notes', 'created_by'];

    public function items() { return $this->hasMany(SalesInvoiceItem::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function salesOrder() { return $this->belongsTo(SalesOrder::class); }
    public function installments() { return $this->hasMany(InvoiceInstallment::class);}
    public function deliveryOrder(): BelongsTo { return $this->belongsTo(DeliveryOrder::class); }

    protected $casts = [
    'due_date' => 'datetime',
    'tanggal' => 'datetime',
    ];
}
