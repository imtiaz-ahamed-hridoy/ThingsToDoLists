<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TieredPrice extends Model
{
    protected $table = 'tiered_prices';

    protected $fillable = [
        'pricing_category_id',
        'min_pax',
        'max_pax',
        'amount',
        'nett_amount',
        'currency',
        'tax_amount',
        'sort_order',
    ];

    protected $casts = [
        'min_pax' => 'integer',
        'max_pax' => 'integer',
        'amount' => 'decimal:2',
        'nett_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function pricingCategory(): BelongsTo
    {
        return $this->belongsTo(OptionPricingCategory::class, 'pricing_category_id');
    }

    public function matchesPaxCount(int $paxCount): bool
    {
        return $paxCount >= $this->min_pax && $paxCount <= $this->max_pax;
    }
}
