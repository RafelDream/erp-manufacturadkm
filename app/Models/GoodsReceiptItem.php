<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\GoodsReceipt;
use App\Models\RawMaterial;
use App\Models\Product;
use App\Models\Unit;

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
        'notes',
    ];

    protected $casts = [
        'quantity_ordered' => 'decimal:3',
        'quantity_received' => 'decimal:3',
        'quantity_remaining' => 'decimal:3',
        'quantity_actual' => 'decimal:3',
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