<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;

    protected $fillable =[
        'nama',
        'email',
        'alamat',
        'telepon',
        'kontak_person',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
