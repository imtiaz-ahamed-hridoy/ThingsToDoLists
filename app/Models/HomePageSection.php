<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasMediaUrls;
use App\Support\ManualSelectionOrder;

class HomePageSection extends Model
{
    use HasMediaUrls;

    protected $fillable = [
        'title',
        'short_description',
        'image',
        'product_ids',
        'sort_order',
        'status',
        'section_type',
    ];

    protected $casts = [
        'product_ids' => 'array',
        'status' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    /**
     * Get the image with full URL.
     */
    public function getImageAttribute($value)
    {
        return $this->resolveMediaUrl($value);
    }

    /**
     * Get the selected products.
     */
    public function products()
    {
        $ids = $this->product_ids ?? [];
        if (empty($ids)) {
            return collect();
        }
        return ManualSelectionOrder::sortModelsByIds(
            Product::whereIn('id', $ids)->get(),
            $ids
        );
    }

    /**
     * Get the section locations.
     */
    public function locations()
    {
        return $this->hasMany(HomePageSectionLocation::class, 'home_page_section_id');
    }

    /**
     * Scope active sections.
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope ordered by sort_order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function getPosterImageAttribute($value)
    {
        return $this->resolveMediaUrl($value);
    }
}
