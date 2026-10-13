<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Traits\HasMediaUrls;

/**
 * ProductVideo — one video for a product.
 *
 * Replaces the JSON blob in products.video_gallery.
 * Supports YouTube, Vimeo, and direct video URLs.
 */
class ProductVideo extends Model
{
    use HasMediaUrls;
    protected $table = 'product_videos';

    protected $fillable = [
        'product_id',
        'provider',
        'source_url',
        'thumbnail_url',
        'preview_url',
        'embed_html',
        'title',
        'description',
        'external_id',
        'provider_name',
        'duration',
        'mime_type',
        'file_size',
        'width',
        'height',
        'is_primary',
        'is_active',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_active'  => 'boolean',
        'duration'   => 'integer',
        'file_size'  => 'integer',
        'width'      => 'integer',
        'height'     => 'integer',
        'sort_order' => 'integer',
        'metadata'   => 'array',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    // ─── Relationships ──────────────────────────────────────────────────

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    // ─── Scopes ─────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    // ─── Accessors ──────────────────────────────────────────────────────

    protected function sourceUrl(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $this->resolveMediaUrl($value),
        );
    }

    protected function thumbnailUrl(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $this->resolveMediaUrl($value),
        );
    }

    protected function previewUrl(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $this->resolveMediaUrl($value),
        );
    }

    /**
     * Get formatted duration (e.g., "2:30").
     */
    public function getFormattedDurationAttribute(): ?string
    {
        if ($this->duration === null) {
            return null;
        }

        $minutes = intdiv($this->duration, 60);
        $seconds = $this->duration % 60;

        return sprintf('%d:%02d', $minutes, $seconds);
    }
}
