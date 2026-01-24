<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'request_number',
        'request_date',
        'request_by',
        'status',
        'approved_by',
        'approved_at',
        'notes',
    ];

    public function items()
    {
        return $this->hasMany(StockRequestItem::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'request_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

