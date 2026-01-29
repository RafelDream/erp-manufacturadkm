<?php

namespace App\Models;

use App\Models\PurchaseRequest;
use App\Models\RawMaterial;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class PurchaseRequestItem extends Model
{
    use HasFactory; 

    protected $fillable = [
        'purchase_request_id',
        'raw_material_id',
        'product_id',
        'unit_id',
        'quantity',
        'reference_no',
        'notes',
    ];

    /**
     * Relasi ke header PR
     */
    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    /**
     * Relasi ke bahan baku (nullable)
     */
    public function rawMaterial()
    {
      return $this->belongsTo(RawMaterial::class);
    }

    /**
     * Relasi ke product (nullable)
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Relasi ke satuan
     */
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}
