<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\HasMediaUrls;

class Testimonial extends Model
{
    use HasFactory, HasMediaUrls;

    protected $table = 'testimonial_settings';

    protected $fillable = [
        'name',
        'country',
        'country_icon',
        'avater',
        'image',
        'description',
        'footer_description',
        'rating',
        'status',
    ];

    public function getImageAttribute($value)
    {
        return $this->resolveMediaUrl($value);
    }

    public function getCountryIconAttribute($value)
    {
        // Country icon can sometimes be raw HTML SVG instead of a URL path.
        if (empty($value)) return $value;
        if (str_starts_with(trim($value), '<')) return $value; // It's HTML
        return $this->resolveMediaUrl($value);
    }

    public function getAvaterAttribute($value)
    {
        return $this->resolveMediaUrl($value);
    }
}
