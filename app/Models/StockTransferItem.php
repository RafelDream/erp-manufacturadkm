<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Models\Product;
use App\Models\StockTransfer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Warehouse;
use App\Models\User;

class StockTransferItem extends Model
{
    protected $fillable = [
        'stock_transfer_id','product_id','quantity'
    ];
    public function stockTransfer() {
        return $this->belongsTo(StockTransfer::class);
    }
    public function product() {
        return $this->belongsTo(Product::class);
    }
}