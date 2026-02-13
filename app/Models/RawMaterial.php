<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RawMaterial extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'raw_materials';

    protected $fillable = [
        'code',
        'name',
        'category',
        'unit',
        'is_active',
        'last_purchase_price',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Relasi ke stok bahan baku di buat di raw_material_stocks
     */
    public function stocks()
    {
        return $this->hasMany(RawMaterialStock::class);
    }

    public function productionUsages()
    {
        return $this->hasMany(ProductionMaterialUsage::class);
    }

    public function stockMovements()
    {
    return $this->hasMany(RawMaterialStockMovement::class);
    }

    /**
     * Scope bahan aktif
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
