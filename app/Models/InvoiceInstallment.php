<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InvoiceInstallment extends Model
{
    use HasFactory;

    // Mass assignment agar bisa disimpan lewat controller
    protected $fillable = [
        'sales_invoice_id',
        'installment_number',
        'amount',
        'fine_paid',
        'payment_date',
        'receipt_no'
    ];

    public function invoice()
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }
}
