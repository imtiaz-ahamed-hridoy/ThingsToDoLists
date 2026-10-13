<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $fillable = [
        'product_id',
        'external_id',
        'city',
        'countryCode',
        'country',
        'address',
        'postal_code',
        'city_id',
        'latitude',
        'longitude',
        'coordinates',
        'location_type',
        'label',
        'is_primary',
        'sort_order',
        'meta',
    ];

    protected $casts = [
        'coordinates' => 'array',
        'meta' => 'array',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'city_id' => 'string',
        'is_primary' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
