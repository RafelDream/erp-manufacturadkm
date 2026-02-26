<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesQuotation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'no_quotation',
        'tanggal',
        'customer_id',
        'cara_bayar',
        'dp_amount',
        'total_price',
        'notes',
        'status',
        'created_by'
    ];

    // 🔥 Casting biar aman secara tipe data
    protected $casts = [
        'tanggal'     => 'date',
        'dp_amount'   => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function items()
    {
        return $this->hasMany(SalesQuotationItem::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    // 🔥 Relasi ke SPK (hasil convert)
    public function salesOrder()
    {
        return $this->hasOne(SalesOrder::class);
    }

    // 🔥 User pembuat quotation
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER METHODS
    |--------------------------------------------------------------------------
    */

    public function isApproved()
    {
        return $this->status === 'approved';
        if (!$quotation->isApproved()) {
            return back()->with('error', 'Quotation belum disetujui');
        }

    }

    public function isConverted()
    {
        return $this->status === 'converted';
    }
}
