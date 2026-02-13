<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillOfMaterialItem extends Model
{
    protected $fillable = [
        'bill_of_material_id',
        'raw_material_id',
        'quantity',
        'unit_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
    ];

    public function billOfMaterial()
    {
        return $this->belongsTo(BillOfMaterial::class);
    }

    public function rawMaterial()
    {
        return $this->belongsTo(RawMaterial::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}
