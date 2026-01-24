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

    public function items() {
        return $this->hasMany(StockTransferItem::class);
    }
}
