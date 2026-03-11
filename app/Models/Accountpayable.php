<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class AccountPayable extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'payable_number',
        'invoice_receipt_id',
        'invoice_id',
        'supplier_id',
        'amount',
        'paid_amount',
        'remaining_amount',
        'invoice_date',
        'due_date',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'paid_amount'      => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'invoice_date'     => 'date',
        'due_date'         => 'date',
    ];

    protected $appends = [];

    public function invoiceReceipt()
    {
        return $this->belongsTo(InvoiceReceipt::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function payments()
    {
        return $this->hasMany(PayablePayment::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Apakah sudah melewati jatuh tempo?
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'unpaid' && $this->due_date < now();
    }

    /**
     * Berapa hari overdue?
     */
    public function getOverdueDaysAttribute(): int
    {
        if (!$this->is_overdue) return 0;
        
        return (int) now()->diffInDays($this->due_date, false);
    }
}