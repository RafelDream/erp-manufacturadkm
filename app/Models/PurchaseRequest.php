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
        'approval_notes',
    ];

    protected $casts = [
        'request_date' => 'date',
    ];

    /**
     * Items PR
     */
    public function items()
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    /**
     * User pembuat PR
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User pemohon
     */
    public function requester()
    {
        return $this->belongsTo(User::class, 'request_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
