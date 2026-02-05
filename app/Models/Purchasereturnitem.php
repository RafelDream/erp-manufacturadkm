<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_return_id',
        'raw_material_id',
        'product_id',
        'unit_id',
        'quantity_return',
        'reason',
        'notes',
    ];

    protected $casts = [
        'quantity_return' => 'decimal:3',
    ];

    /**
     * Relasi ke Purchase Return
     */
    public function purchaseReturn()
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    /**
     * Relasi ke Raw Material
     */
    public function rawMaterial()
    {
        return $this->belongsTo(RawMaterial::class);
    }

    /**
     * Relasi ke Product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Relasi ke Unit
     */
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}