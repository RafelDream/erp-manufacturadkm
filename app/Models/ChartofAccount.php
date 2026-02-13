<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChartOfAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'type',
        'category',
        'is_cash',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_cash' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function initialBalances()
    {
        return $this->hasMany(InitialBalance::class, 'account_id');
    }

    /**
     * Filter berdasarkan tipe akun tertentu.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Mengambil hanya akun kas yang berstatus aktif..
     */
    public function scopeCashAccounts($query)
    {
        return $query->where('is_cash', true)->where('is_active', true);
    }

    /**
     * Filter hanya akun yang berstatus aktif.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}