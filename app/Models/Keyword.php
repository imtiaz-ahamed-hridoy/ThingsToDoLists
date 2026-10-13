<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Keyword extends Model
{
    protected $fillable = [
        'product_id',
        'provider',
        'name',
        'slug',
        'type',
        'sort_order',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'sort_order' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
