<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Product;
use App\Traits\ImagePathTrait;

class Image extends Model
{
    use ImagePathTrait;

    protected $fillable = [
        'product_id',
        'original',
        'large',
        'preview',
        'alt_text',
        'external_id',
        'mime_type',
        'file_size',
        'extension',
        'provider_path',
        'sort_order',
        'meta',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'sort_order' => 'integer',
        'meta' => 'array',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Override getters to return full URLs directly
    public function getOriginalAttribute($value): ?string
    {
        return $this->fullImageUrl(is_array($value) ? ($value[0] ?? null) : $value);
    }

    public function getLargeAttribute($value): ?string
    {
        return $this->fullImageUrl(is_array($value) ? ($value[0] ?? null) : $value);
    }

    public function getPreviewAttribute($value): ?string
    {
        return $this->fullImageUrl(is_array($value) ? ($value[0] ?? null) : $value);
    }
}
