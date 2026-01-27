<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawMaterialStockOutItem extends Model
{
    use HasFactory;

    protected $table = 'raw_material_stock_out_items';

    protected $fillable = [
        'raw_material_stock_out_id',
        'raw_material_id',
        'quantity',
        'unit_id',
        'notes',
    ];

    protected $casts = ['quantity' => 'double',];
    /**
     * Relasi ke header Stock Out
     */
    public function stockOut()
    {
        return $this->belongsTo(
            RawMaterialStockOut::class,
            'raw_material_stock_out_id'
        );
    }

    /**
     * Relasi ke bahan baku
     */
    public function rawMaterial()
    {
        return $this->belongsTo(RawMaterial::class, 'raw_material_id');
    }
    
    /**
     * Relasi ke satuan
     */
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}
