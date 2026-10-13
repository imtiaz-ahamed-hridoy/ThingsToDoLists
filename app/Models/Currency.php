<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'symbol',
        'exchange_rate',
        'is_active',
    ];

    protected $casts = [
        'exchange_rate' => 'float',
        'is_active' => 'boolean',
    ];
}
