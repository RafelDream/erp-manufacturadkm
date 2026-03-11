<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class RawMaterialStockMovement extends Model
{
    use HasFactory;

    protected $table = 'raw_material_stock_movements';

    protected $fillable = [
        'raw_material_id',
        'warehouse_id',
        'movement_type',
        'quantity',
        'unit_price',    
        'total_price',     
        'reference_type',
        'reference_id',
        'notes',       
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2', 
        'total_price' => 'decimal:2', 
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

    /**
     * Relasi polymorphic (dokumen sumber)
     */
    public function reference()
    {
        return $this->morphTo();
    }

    /**
     * User pencatat
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
