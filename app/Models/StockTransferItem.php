<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class StockTransferItem extends Model
{
    protected $fillable = [
        'stock_transfer_id','product_id','quantity'
    ];
}
