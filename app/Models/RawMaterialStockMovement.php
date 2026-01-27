<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class RawMaterialStockMovement extends Model
{
    use HasFactory;

    protected $table = 'raw_material_stock_movements';

    protected $fillable = [
        'raw_material_id',
        'warehouse_id',
        'movement_type',
        'quantity',
        'reference_type',
        'reference_id',
        'created_by',
    ];

    /**
     * Relasi ke bahan baku
     */
    public function rawMaterial()
    {
        return $this->belongsTo(RawMaterial::class);
    }

    /**
     * Relasi ke gudang
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Relasi polymorphic (dokumen sumber)
     */
    public function reference()
    {
        return $this->morphTo();
    }

    /**
     * User pencatat
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
