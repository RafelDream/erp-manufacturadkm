<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'kode',
        'name',
        'unit_id',
        'tipe',
        'volume',
        'harga',
        'is_returnable',
        'is_active',
    ];

    protected $casts = [
        'is_returnable' => 'boolean',
        'is_active' => 'boolean',
        'harga' => 'decimal:2',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function stockRequestItems()
    {
        return $this->hasMany(StockRequestItem::class);
    }
    
    public function stockAdjustmentItems()
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }
}
