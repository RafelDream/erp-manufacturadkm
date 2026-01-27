<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RawMaterialStock extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'raw_material_stocks';

    protected $fillable = [
        'raw_material_id',
        'warehouse_id',
        'quantity',
        'status',
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
}
