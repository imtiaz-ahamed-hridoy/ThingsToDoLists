<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TempProduct extends Model
{
    protected $fillable = [
        'product_api_id',
        'api_platform',
        'title',
        'description',
        'slug',
        'vendor_name',
        'price',
        'rating',
        'review_count',
        'city',
        'thumbnail',
        'raw_data',
        'status',
        'last_import_status',
        'last_import_reason',
        'last_import_attempted_at',
    ];

    /**
     * Cast fields to proper types
     */
    protected $casts = [
        'raw_data' => 'array',
        'price'    => 'float',
        'rating'   => 'float',
        'review_count' => 'integer',
        'last_import_attempted_at' => 'datetime',
    ];

    /**
     * Optional: City relationship if you have a City model
     */
    // public function city()
    // {
    //     return $this->belongsTo(City::class, 'city_id');
    // }
}
