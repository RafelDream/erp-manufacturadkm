<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class ProductionMaterialUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'production_order_id',
        'raw_material_id',
        'quantity_used',
        'unit_cost',
        'total_cost',
    ];

    protected $casts = [
        'quantity_used' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    public function productionOrder()
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function rawMaterial()
    {
        return $this->belongsTo(RawMaterial::class);
    }
}