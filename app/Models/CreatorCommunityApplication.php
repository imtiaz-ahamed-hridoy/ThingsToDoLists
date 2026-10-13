<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreatorCommunityApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'email',
        'name',
        '',
        'instagram_handle',
        'instagram_followers',
        'youtube_handle',
        'tiktok_handle',
        'website_url',
        'has_base_location',
        'country',
        'city',
        'discovered_via',
        'discovered_via_other',
        'referred_by',
        'status',
        'raw_payload',
    ];

    protected $casts = [
        'has_base_location' => 'boolean',
        'instagram_followers' => 'integer',
        'raw_payload' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
