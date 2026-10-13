<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Price extends Model
{
    protected $table = 'prices';

    protected $fillable = [
        'product_id',
        'product_option_id',
        'pricing_category_id',
        'idempotency_key',
        'provider',
        'source_type',
        'price_kind',
        'external_id',
        'rate_id',
        'rate_name',
        'ticket_type_id',
        'ticket_type_name',
        'category_code',
        'category_name',
        'valid_from',
        'valid_to',
        'selling_price',
        'nett_price',
        'original_price',
        'recommended_price',
        'merchant_price',
        'currency',
        'tax_amount',
        'tax_percentage',
        'discount_percentage',
        'discounted_price',
        'is_default',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_to' => 'date',
        'selling_price' => 'decimal:2',
        'nett_price' => 'decimal:2',
        'original_price' => 'decimal:2',
        'recommended_price' => 'decimal:2',
        'merchant_price' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'tax_percentage' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'discounted_price' => 'decimal:2',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productOption(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'product_option_id');
    }

    public function pricingCategory(): BelongsTo
    {
        return $this->belongsTo(OptionPricingCategory::class, 'pricing_category_id');
    }

    /**
     * Convenience relation so existing code can resolve tiers from price rows.
     */
    public function tieredPrices(): HasMany
    {
        return $this->hasMany(TieredPrice::class, 'pricing_category_id', 'pricing_category_id')
            ->orderBy('min_pax');
    }

    public function getTierForPax(int $paxCount): ?TieredPrice
    {
        return $this->tieredPrices
            ->first(fn (TieredPrice $tier) => $tier->matchesPaxCount($paxCount));
    }

    public function effectivePrice(int $paxCount = 1): float
    {
        $tier = $this->getTierForPax($paxCount);

        return $tier ? (float) $tier->amount : (float) $this->selling_price;
    }
}
