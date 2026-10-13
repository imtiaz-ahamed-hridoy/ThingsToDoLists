<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\City;
use App\Traits\HasMediaUrls;
use Illuminate\Database\Eloquent\Casts\Attribute;

class HomePageSectionLocation extends Model
{
    use HasMediaUrls;

    protected $fillable = [
        'home_page_section_id',
        'location_type',
        'location_id',
        'image',
    ];

    /**
     * Get the image with full URL.
     */
    protected function image(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $this->resolveMediaUrl($value),
        );
    }

    public function section()
    {
        return $this->belongsTo(HomePageSection::class, 'home_page_section_id');
    }

    /**
     * Relation to City model (used when location_type = 'city').
     */
    public function city()
    {
        return $this->belongsTo(City::class, 'location_id');
    }
}
