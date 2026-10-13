<?php

namespace App\Models;

use App\Traits\HasMediaUrls;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DestinationHighlight extends Model
{
    use HasFactory, HasMediaUrls;

    protected $fillable = [
        'name',
        'destination_type',
        'destination_id',
        'image',
        'description',
        'status',
    ];

    protected $casts = [
        'destination_id' => 'integer',
    ];

    /**
     * The City record this highlight belongs to.
     * Works for both city-type and country-type highlights
     * since both reference the cities table.
     */
    public function destination(): BelongsTo
    {
        return $this->belongsTo(City::class, 'destination_id');
    }

    /**
     * Products assigned to this highlight.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'destination_highlight_product',
            'destination_highlight_id',
            'product_id'
        )->withPivot('position')
            ->orderByPivot('position');
    }

    /**
     * Scope: only active highlights.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Get the resolved destination name.
     */
    public function getDestinationNameAttribute(): string
    {
        if (!$this->destination) {
            return '';
        }

        return $this->destination_type === 'city'
            ? $this->destination->city_name
            : $this->destination->country_name;
    }

    /**
     * Accessor for the image URL.
     */
    protected function image(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $this->resolveMediaUrl($value),
        );
    }
}
