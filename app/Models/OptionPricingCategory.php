<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OptionPricingCategory extends Model
{
    protected $table = 'option_pricing_categories';

    protected $fillable = [
        'product_option_id',
        'provider',
        'idempotency_key',
        'external_id',
        'category_code',
        'category_label',
        'age_from',
        'age_to',
        'occupancy',
        'group_size',
        'selling_price',
        'nett_price',
        'original_price',
        'recommended_price',
        'merchant_price',
        'currency',
        'min_purchase_qty',
        'max_purchase_qty',
        'is_default',
        'is_age_qualified',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'age_from' => 'integer',
        'age_to' => 'integer',
        'occupancy' => 'integer',
        'group_size' => 'integer',
        'selling_price' => 'decimal:2',
        'nett_price' => 'decimal:2',
        'original_price' => 'decimal:2',
        'recommended_price' => 'decimal:2',
        'merchant_price' => 'decimal:2',
        'min_purchase_qty' => 'integer',
        'max_purchase_qty' => 'integer',
        'is_default' => 'boolean',
        'is_age_qualified' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function productOption(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'product_option_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(Price::class, 'pricing_category_id')
            ->orderBy('valid_from')
            ->orderBy('sort_order');
    }

    public function tieredPrices(): HasMany
    {
        return $this->hasMany(TieredPrice::class, 'pricing_category_id')
            ->orderBy('min_pax')
            ->orderBy('sort_order');
    }

    public function availabilityCategoryPrices(): HasMany
    {
        return $this->hasMany(AvailabilityCategoryPrice::class, 'pricing_category_id');
    }

    public function getAgeRangeAttribute(): ?string
    {
        if ($this->age_from === null && $this->age_to === null) {
            return null;
        }

        return ($this->age_from ?? 0) . ' - ' . ($this->age_to ?? '99+');
    }
}
