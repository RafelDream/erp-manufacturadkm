<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'kode',
        'name',
        'lokasi',
        'deskripsi',
        'is_active',
    ];

        public function stocks()
    {
        return $this->hasMany(Stock::class);
    }

    public function stockAdjustments()
    {
        return $this->hasMany(StockAdjustment::class);
    }

    public function rawMaterialStock()
    {
        return $this->hasMany(RawMaterialStock::class);
    }

    public function deliveryOrders()
    {
        return $this->hasMany(DeliveryOrder::class, 'warehouse_id');
    }

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
