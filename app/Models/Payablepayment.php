<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayablePayment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'payment_number',
        'account_payable_id',
        'supplier_id',
        'payment_method',
        'payment_account_id',
        'payment_date',
        'amount',
        'reference_number',
        'bank_name',
        'account_number',
        'notes',
        'status',
        'journal_entry_id',
        'created_by',
        'confirmed_by',
        'confirmed_at',
        'cancelled_by',
        'cancelled_at',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'payment_date' => 'date:Y-m-d',
        'confirmed_at' => 'date:Y-m-d',
        'cancelled_at' => 'date:Y-m-d',
    ];

    public function accountPayable()
    {
        return $this->belongsTo(AccountPayable::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function paymentAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'payment_account_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * Label metode pembayaran untuk tampilan
     */
    public function getPaymentMethodLabelAttribute(): string
    {
        return match($this->payment_method) {
            'cash'          => 'Kas',
            'bank_transfer' => 'Transfer Bank',
            'credit_card'   => 'Kartu Kredit',
            'giro_cek'      => 'Giro / Cek',
            default         => $this->payment_method,
        };
    }
}