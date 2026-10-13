<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Traits\HasMediaUrls;

class SystemSetting extends Model
{
    use HasFactory, HasMediaUrls;

    protected $fillable = [
        'title',
        'email',
        'number',
        'system_name',
        'address',
        'copyright_text',
        'logo',
        'favicon',
        'description',
        'facebook',
        'twitter',
        'linkedin',
        'instagram',
        'whatsapp',
        'telegram',
        'supplier_guide_title',
        'supplier_guide_description',
        'supplier_guide_link_or_iframe',
        'supplier_guide_media',
        'supplier_guide_media_type',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function getLogoAttribute($value)
    {
        if (empty($value)) {
            return $value;
        }
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }
        if (request()->is('api/*')) {
            return $this->resolveMediaUrl($value);
        }
        return $value;
    }

    public function getFaviconAttribute($value)
    {
        if (empty($value)) {
            return $value;
        }
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }
        if (request()->is('api/*')) {
            return $this->resolveMediaUrl($value);
        }
        return $value;
    }

    public function getSupplierGuideMediaAttribute($value)
    {
        if (empty($value)) {
            return $value;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        if (request()->is('api/*')) {
            return $this->resolveMediaUrl($value);
        }

        return $value;
    }

    protected static function booted()
    {
        static::updating(function ($systemSetting) {
            if ($systemSetting->isDirty('favicon')) {
                $oldFavicon = $systemSetting->getOriginal('favicon');
                if ($oldFavicon && Storage::disk('public')->exists($oldFavicon)) {
                    Storage::disk('public')->delete($oldFavicon);
                }
            }

            if ($systemSetting->isDirty('logo')) {
                $oldLogo = $systemSetting->getOriginal('logo');
                if ($oldLogo && Storage::disk('public')->exists($oldLogo)) {
                    Storage::disk('public')->delete($oldLogo);
                }
            }

            if ($systemSetting->isDirty('supplier_guide_media')) {
                $oldGuideMedia = $systemSetting->getOriginal('supplier_guide_media');
                if ($oldGuideMedia && ! filter_var($oldGuideMedia, FILTER_VALIDATE_URL) && Storage::disk('public')->exists($oldGuideMedia)) {
                    Storage::disk('public')->delete($oldGuideMedia);
                }
            }
        });
    }
}
