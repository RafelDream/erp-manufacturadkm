<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;


class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = 
    ['kode_customer', 'name', 'phone', 'address', 'city', 'type', 'is_active'];

    public function deliveryOrders()
    {
        return $this->hasMany(DeliveryOrder::class, 'customer_id');
    }
}
