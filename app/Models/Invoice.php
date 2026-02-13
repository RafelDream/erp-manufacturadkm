<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes;

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

    public function invoiceReceipt()
    {
        return $this->belongsTo(InvoiceReceipt::class);
    }

    /**
     * Periksa apakah faktur sudah jatuh tempo.
     */
    public function getIsOverdueAttribute()
    {
        return $this->due_date < now() && $this->invoiceReceipt->status !== 'approved';
    }

    /**
     * Dapatkan informasi jumlah hari hingga tanggal jatuh tempo.
     */
    public function getDaysUntilDueAttribute()
    {
        return now()->diffInDays($this->due_date, false);
    }
}