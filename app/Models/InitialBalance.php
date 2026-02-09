<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InitialBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'year',
        'account_id',
        'debit',
        'credit',
        'budget',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'year' => 'integer',
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'budget' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function account()
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}