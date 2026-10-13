<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Storage;
use App\Traits\HasMediaUrls;

class User extends Authenticatable
{
    use HasMediaUrls;
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'avatar',
        'address',
        'dob',
        'password',
        'role',
        'is_online',
        'is_agree',
        'preferred_currency'
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_online' => 'boolean',
            'dob' => 'date',
            'is_agree' => 'boolean',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'email_verified_at',
        'created_at',
        'updated_at',
        "reset_code",
        "reset_code_expires_at",
    ];

    protected $appends = [
        //
    ];

    public function emaillog()
    {
        return $this->hasMany(EmailLog::class);
    }

    public function review()
    {
        return $this->hasMany(Review::class, 'user_id');
    }

    public function bookings()
    {
        return $this->hasMany(BookingInfo::class, 'user_id');
    }

    public function getAvatarAttribute($value)
    {
        return $this->resolveMediaUrl($value);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'status'])
            ->logOnlyDirty()
            ->useLogName('user');
    }


    public function vendor()
    {
        return $this->hasOne(Vendor::class, 'user_id');
    }

    public function creator()
    {
        return $this->hasOne(Creator::class, 'user_id');
    }

    public function creatorCommunityApplications()
    {
        return $this->hasMany(CreatorCommunityApplication::class, 'user_id');
    }

    public function creatorSponsoredExperienceApplications()
    {
        return $this->hasMany(CreatorSponsoredExperienceApplication::class, 'user_id');
    }

    public function recentlyViewed()
    {
        return $this->belongsToMany(Product::class, 'user_recently_viewed', 'user_id', 'product_id')->withTimestamps();
    }
}
