<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Creator extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'creator_type',
        'organization_name',
        'currency',
        'country',
        'instagram_handle',
        'youtube_account',
        'tiktok_account',
        'main_channel',
    ];

    /**
     * Get the user that owns the creator.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
