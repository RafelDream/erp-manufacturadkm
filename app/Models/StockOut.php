<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\StockOutItem;
use App\Models\StockRequest;

class StockOut extends Model
{
    protected $fillable = [
        'stock_request_id',
        'warehouse_id',
        'out_date',
        'notes',
        'created_by'
    ];

    public function items()
    {
        return $this->hasMany(StockOutItem::class);
    }

    public function request()
    {
        return $this->belongsTo(StockRequest::class, 'stock_request_id');
    }
}
