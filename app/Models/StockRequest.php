<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockRequest extends Model
{
    use SoftDeletes;

    protected $table = 'stock_requests';

    protected $fillable = [
        'request_number',
        'request_date',
        'request_by',
        'status',
        'notes',
    ];

    protected $casts = [
        'request_date' => 'date',
    ];

    /* ======================
     | RELATIONSHIPS
     ====================== */

    // Pemohon permintaan (User)
    public function requester()
    {
        return $this->belongsTo(User::class, 'request_by');
    }

    // Item permintaan
    public function items()
    {
        return $this->hasMany(StockRequestItem::class);
    }
}
