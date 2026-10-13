<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreatorSponsoredExperienceApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'creator_community_application_id',
        'is_community_member',
        'name',
        '',
        'email',
        'instagram_url',
        'preferred_currency',
        'reel_views_range',
        'planned_experience_date',
        'no_go_experiences_accepted',
        'feature_consent',
        'status',
        'raw_payload',
    ];

    protected $casts = [
        'is_community_member' => 'boolean',
        'no_go_experiences_accepted' => 'boolean',
        'feature_consent' => 'boolean',
        'planned_experience_date' => 'date',
        'raw_payload' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function communityApplication()
    {
        return $this->belongsTo(CreatorCommunityApplication::class, 'creator_community_application_id');
    }
}
