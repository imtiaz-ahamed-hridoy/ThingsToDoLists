<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasMediaUrls;

class HomePageBanner extends Model
{
    use HasMediaUrls;

    protected $fillable = [
        'title',
        'video_path',
        'video_url',
        'poster_image',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function getVideoPathAttribute($value)
    {
        return $this->resolveMediaUrl($value);
    }

    public function getPosterImageAttribute($value)
    {
        return $this->resolveMediaUrl($value);
    }
}
