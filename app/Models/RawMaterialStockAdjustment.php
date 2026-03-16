<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RawMaterialStockAdjustment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'adjustment_no',
        'raw_material_id',
        'warehouse_id',
        'before_quantity',
        'after_quantity',
        'difference',
        'type',
        'reason',
        'created_by',
    ];

    // Tambahkan di dalam class RawMaterialStockAdjustment
    protected $casts = [
    'before_quantity' => 'decimal:2',
    'after_quantity' => 'decimal:2',
    'difference' => 'decimal:2',
    ];

    protected static function booted()
    {
            static::creating(function ($model) {
            // Otomatis buat nomor adjustment jika kosong
            if (!$model->adjustment_no) {
            $date = now()->format('Ymd');
            $last = self::whereDate('created_at', now())->count() + 1;
            $model->adjustment_no = "ADJ-{$date}-" . str_pad($last, 4, '0', STR_PAD_LEFT);
        }
    });
    }

    public function rawMaterial()
    {
        return $this->belongsTo(RawMaterial::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
