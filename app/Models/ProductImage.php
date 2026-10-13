<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Traits\HasMediaUrls;

/**
 * ProductImage — one image for a product.
 *
 * Replaces the JSON blob in products.image_gallery.
 * Supports Bokun (derived URLs), GlobalTix (media paths), and Supplier uploads.
 */
class ProductImage extends Model
{
    use HasMediaUrls;
    protected $table = 'product_images';

    protected $fillable = [
        'product_id',
        'provider',
        'original_url',
        'large_url',
        'preview_url',
        'alt_text',
        'external_id',
        'provider_path',
        'mime_type',
        'extension',
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

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    // ─── Accessors ──────────────────────────────────────────────────────

    protected function originalUrl(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $this->resolveMediaUrl($value),
        );
    }

    protected function largeUrl(): Attribute
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
     * Get the best available URL (large → original → preview).
     */
    public function getBestUrlAttribute(): ?string
    {
        return $this->large_url ?? $this->original_url ?? $this->preview_url;
    }

    /**
     * Get the thumbnail URL (preview → large → original).
     */
    public function getThumbnailAttribute(): ?string
    {
        return $this->preview_url ?? $this->large_url ?? $this->original_url;
    }
}
