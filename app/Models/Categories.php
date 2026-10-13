<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Traits\HasMediaUrls;

class Categories extends Model
{
    use HasFactory, HasMediaUrls;

    protected $guarded = [];

    protected $casts = [
        'status' => 'integer',
        'parent_category_id' => 'integer',
        'city_id' => 'integer',
        'location_country' => 'string',
        'location_country_code' => 'string',
        'show_in_search' => 'boolean',
        'search_sort_order' => 'integer',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function parentCategory()
    {
        return $this->belongsTo(Categories::class, 'parent_category_id');
    }

    public function categoryType()
    {
        return $this->belongsTo(CategoryType::class, 'type', 'slug');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'category_id')
            ->orderBy('category_position')
            ->orderBy('id');
    }

    public function product()
    {
        return $this->products();
    }

    protected function image(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $this->resolveMediaUrl($value),
        );
    }

    protected function icon(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $this->resolveMediaUrl($value),
        );
    }
}
