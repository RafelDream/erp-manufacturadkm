<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Models\StockTransferItem;


class StockTransfer extends Model
{
    protected $fillable = [
        'kode',
        'dari_warehouse_id',
        'ke_warehouse_id',
        'transfer_date',
        'status',
        'notes',
        'created_by',
        'approved_by'
    ];

    protected $casts = [
        'transfer_date' => 'date',
    ];

    public function items() {
        return $this->hasMany(StockTransferItem::class);
    }

    public function dariWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'dari_warehouse_id');
    }

    public function keWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'ke_warehouse_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

