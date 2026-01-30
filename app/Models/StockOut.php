<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\StockOutItem;
use App\Models\StockRequest;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockOut extends Model
{
    use SoftDeletes;
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
        public function stockRequest()
    {
        return $this->belongsTo(StockRequest::class, 'stock_request_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    } 
}
