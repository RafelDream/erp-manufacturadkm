<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;


class RawMaterialStockOut extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'warehouse_id',
        'issued_at',
        'notes',
        'status',
        'created_by',
    ];

    protected $casts = ['issued_at' => 'date',];

    /**
     * Relasi Gudang asal
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * User pembuat dokumen
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relasi ke item detail
     */
    public function items()
    {
        return $this->hasMany(RawMaterialStockOutItem::class);
    }

    /**
     * Relasi ke stock movement (OUT)
     */
    public function stockMovements()
    {
        return $this->morphMany(
            RawMaterialStockMovement::class,
            'reference'
        );
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancel';
    }
}
