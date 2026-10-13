<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Traits\HasMediaUrls;

class City extends Model
{
    use HasFactory, HasMediaUrls;

    protected $fillable = [
        'city_name',
        'city_short_code',
        'city_is_capital',
        'slug',
        'country_name',
        'country_short_code',
        'country_slug',
        'currency_code',
        'mobile_prefix',
        'title',
        'short_description',
        'thumbnail_image',
        'gallery_image',
        'item_status',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function tempProduct()
    {
        return $this->hasMany(TempProduct::class);
    }

    protected function thumbnailImage(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $this->resolveMediaUrl($value),
        );
    }

    protected function galleryImage(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (empty($value)) return $value;
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    return array_map(fn($img) => $this->resolveMediaUrl($img), $decoded);
                }
                return $this->resolveMediaUrl($value);
            },
        );
    }
}
