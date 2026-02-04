<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_receipt_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'amount',
        'notes',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'amount' => 'decimal:2',
    ];

    /**
     * Relasi ke Invoice Receipt
     */
    public function invoiceReceipt()
    {
        return $this->belongsTo(InvoiceReceipt::class);
    }

    /**
     * Check if invoice is overdue
     */
    public function getIsOverdueAttribute()
    {
        return $this->due_date < now() && $this->invoiceReceipt->status !== 'approved';
    }

    /**
     * Get days until due date
     */
    public function getDaysUntilDueAttribute()
    {
        return now()->diffInDays($this->due_date, false);
    }
}