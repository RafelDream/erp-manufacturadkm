<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RawMaterialStockIn extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'stock_in_number',
        'stock_in_date',
        'warehouse_id',
        'notes',
        'status',
        'created_by',
    ];

    public function items()
    {
        return $this->hasMany(RawMaterialStockInItem::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
