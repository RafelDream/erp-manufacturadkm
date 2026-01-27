<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Stock;

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

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
