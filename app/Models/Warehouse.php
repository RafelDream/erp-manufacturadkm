<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Warehouse extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'kode',
        'name',
        'lokasi',
        'deskripsi',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
