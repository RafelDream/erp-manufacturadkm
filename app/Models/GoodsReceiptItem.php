<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceiptItem extends Model
{
    protected $fillable = [
        'goods_receipt_id',
        'raw_material_id',
        'product_id',
        'unit_id',
        'quantity_ordered',
        'quantity_received',
        'quantity_remaining',
        'quantity_actual',
        "unit_price",
        "total_price",
        'notes',
    ];

    protected $casts = [
        'quantity_ordered'   => 'float',
        'quantity_received'  => 'float',
        'quantity_remaining' => 'float',
        'quantity_actual'    => 'float',
        'unit_price'         => 'float',
        'total_price'        => 'float',

    ];

    public function goodsReceipt()
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function rawMaterial()
    {
        return $this->belongsTo(RawMaterial::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}