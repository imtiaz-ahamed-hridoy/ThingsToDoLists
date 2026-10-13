<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvailabilityCategoryPrice extends Model
{
    protected $table = 'availability_category_prices';

    protected $fillable = [
        'availability_id',
        'pricing_category_id',
        'category_external_id',
        'category_code',
        'category_label',
        'selling_price',
        'nett_price',
        'original_price',
        'recommended_price',
        'merchant_price',
        'tax_amount',
        'currency',
        'available_qty',
        'min_qty',
        'max_qty',
        'metadata',
    ];

    protected $casts = [
        'selling_price' => 'decimal:2',
        'nett_price' => 'decimal:2',
        'original_price' => 'decimal:2',
        'recommended_price' => 'decimal:2',
        'merchant_price' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'available_qty' => 'integer',
        'min_qty' => 'integer',
        'max_qty' => 'integer',
        'metadata' => 'array',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function availability(): BelongsTo
    {
        return $this->belongsTo(AvailabilityCheck::class, 'availability_id');
    }

    public function pricingCategory(): BelongsTo
    {
        return $this->belongsTo(OptionPricingCategory::class, 'pricing_category_id');
    }
}
