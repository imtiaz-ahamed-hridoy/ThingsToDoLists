<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    protected $fillable = [
        'product_id',
        'sourceUrl',
        'thumbnailUrl',
        'previewUrl',
        'html',
        'providerName',
        'title',
        'duration',
    ];

    protected $casts = [
        'duration' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
