<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockTransferItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_transfer_id',
        'itemable_id',
        'itemable_type',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'float',
    ];

    /**
     * Relasi polymorphic ke Product atau RawMaterial.
     */
    public function itemable(): MorphTo
    {
        return $this->morphTo();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class);
    }

    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
    }

    /**
     * Accessor untuk mendapatkan nama item secara dinamis.
     */
    public function getItemNameAttribute(): string
    {
        return $this->itemable->name ?? 'Unknown Item';
    }
}