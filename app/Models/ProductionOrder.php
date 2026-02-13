<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'production_number',
        'product_id',
        'bom_id',
        'warehouse_id',
        'production_date',
        'quantity_plan',
        'quantity_actual',
        'quantity_waste',
        'status',
        'notes',
        'operator',
        'total_material_cost',
        'labor_cost',
        'overhead_cost',
        'total_production_cost',
        'hpp_per_unit',
        'created_by',
        'released_by',
        'released_at',
        'started_by',
        'started_at',
        'completed_by',
        'completed_at',
        'completion_notes',
    ];

    protected $casts = [
        'production_date' => 'date',
        'quantity_plan' => 'decimal:3',
        'quantity_actual' => 'decimal:3',
        'quantity_waste' => 'decimal:3',
        'total_material_cost' => 'decimal:2',
        'labor_cost' => 'decimal:2',
        'overhead_cost' => 'decimal:2',
        'total_production_cost' => 'decimal:2',
        'hpp_per_unit' => 'decimal:2',
        'released_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function bom()
    {
        return $this->belongsTo(BillOfMaterial::class, 'bom_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function releaser()
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function starter()
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function completer()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function executor()
    {
        return $this->starter();
    }

    public function materialUsages()
    {
        return $this->hasMany(ProductionMaterialUsage::class);
    }
}