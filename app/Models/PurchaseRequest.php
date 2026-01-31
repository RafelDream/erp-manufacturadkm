<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'kode',
        'request_date',
        'type',
        'department',
        'request_by',
        'status',
        'notes',
        'approved_by',
        'approved_at',
        'completed_at',
        'completed_by',
        'created_by', 
        'approval_notes',
    ];

    protected $casts = [
        'request_date' => 'date',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime', 
    ];

    public function items()
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'request_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ✅ TAMBAHKAN RELASI INI
    public function completer()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    // ✅ TAMBAHKAN RELASI INI
    public function purchaseOrder()
    {
        return $this->hasOne(PurchaseOrder::class);
    }
}