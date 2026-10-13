<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureType extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'status' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'feature_type_product', 'feature_type_id', 'product_id')
            ->withPivot('position')
            ->orderByPivot('position')
            ->withTimestamps();
    }
}
