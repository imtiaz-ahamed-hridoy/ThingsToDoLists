<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductOption extends Model
{
    protected $table = 'product_options';

    protected $fillable = [
        'product_id',
        'provider',
        'external_id',
        'name',
        'description',
        'currency',
        'ticket_format',
        'ticket_validity',
        'defined_duration',
        'is_dynamic_pricing',
        'is_capacity',
        'is_cancellable',
        'time_slot',
        'visit_date',
        'advance_booking',
        'questions',
        'inclusions',
        'exclusions',
        'cancellation_policy',
        'cancellation_notes',
        'publish_start',
        'publish_end',
        'redeem_start',
        'redeem_end',
        'is_default',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'is_dynamic_pricing' => 'boolean',
        'is_capacity' => 'boolean',
        'is_cancellable' => 'boolean',
        'time_slot' => 'array',
        'visit_date' => 'array',
        'advance_booking' => 'array',
        'questions' => 'array',
        'inclusions' => 'array',
        'exclusions' => 'array',
        'cancellation_notes' => 'array',
        'publish_start' => 'date',
        'publish_end' => 'date',
        'redeem_start' => 'date',
        'redeem_end' => 'date',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function pricingCategories(): HasMany
    {
        return $this->hasMany(OptionPricingCategory::class, 'product_option_id')
            ->orderByDesc('is_default')
            ->orderBy('sort_order');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(Price::class, 'product_option_id')
            ->orderByDesc('is_default')
            ->orderBy('sort_order');
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(AvailabilityCheck::class, 'product_option_id')
            ->orderBy('available_date')
            ->orderBy('start_time');
    }
}
