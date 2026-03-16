<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = [
        'kode',
        'name',
        'unit_id',
        'tipe',
        'stock',
        'volume',
        'harga',
        'hpp_terakhir',
        'hpp_updated_at',
        'is_returnable',
        'is_active',
    ];

    protected $casts = [
        'is_returnable' => 'boolean',
        'is_active' => 'boolean',
        'harga' => 'decimal:2',
        'hpp_terakhir' => 'decimal:2',
        'hpp_updated_at' => 'datetime',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function stocks()
    {
    return $this->hasMany(Stock::class);
    }

    public function stockRequestItems()
    {
        return $this->hasMany(StockRequestItem::class);
    }
    
    public function stockAdjustmentItems()
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }

    public function deliveryOrderItems()
    {
        return $this->hasMany(DeliveryOrderItem::class, 'product_id');
    }

    public function returnItems()
    {
        return $this->hasMany(SalesReturnItem::class);
    }

    public function boms()
    {
        return $this->hasMany(BillOfMaterial::class);
    }

    // Jika ingin ambil stok per gudang tertentu
    public function stockAtWarehouse($warehouseId)
    {
    return $this->stocks()->where('warehouse_id', $warehouseId)->first();
    }
}
