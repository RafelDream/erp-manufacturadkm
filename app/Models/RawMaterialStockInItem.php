<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RawMaterialStockInItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'raw_material_stock_in_id',
        'raw_material_id',
        'quantity',
    ];

    public function stockIn()
    {
        return $this->belongsTo(RawMaterialStockIn::class);
    }

    public function rawMaterial()
    {
        return $this->belongsTo(RawMaterial::class);
    }


}
