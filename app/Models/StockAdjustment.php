<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Psy\TabCompletion\Matcher\FunctionsMatcher;

class StockAdjustment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'adjustment_number',
        'adjustment_date',
        'warehouse_id',
        'reason',
        'notes',
        'status',
        'created_by',
    ];

    /**
     * Konversi tipe data otomatis.
     */
    protected $casts = [
        'adjustment_date' => 'date',
    ];

    /** 
    * Relasi ke Gudang
    */

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Relasi ke User
     */

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relasi ke Item Adjustment
     */

    public function items()
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }

    protected static function booted()
    {
        // Proteksi agar data yang sudah 'approved' tidak bisa diubah
        static::updating(function ($model) {
            if ($model->isDirty('status') && $model->getOriginal('status') === 'approved') {
                throw new \Exception('Data tidak dapat diubah karena status sudah diposting.');
            }
        });

        // Proteksi agar data yang sudah 'approved' tidak bisa dihapus
        static::deleting(function ($model) {
            if ($model->status === 'approved') {
                throw new \Exception('Data yang sudah diposting tidak dapat dihapus.');
            }
        });
    }
}
