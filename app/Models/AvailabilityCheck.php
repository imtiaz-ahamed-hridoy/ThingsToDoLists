<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AvailabilityCheck extends Model
{
    use HasFactory;

    protected $table = 'availability';

    protected $fillable = [
        'product_id',
        'product_option_id',
        'idempotency_key',
        'provider',
        'external_id',
        'available_date',
        'start_time',
        'end_time',
        'start_at',
        'time_zone',
        'capacity_total',
        'capacity_booked',
        'capacity_reserved',
        'unlimited_availability',
        'sold_out',
        'min_participants',
        'max_participants',
        'display_price',
        'currency',
        'is_active',
        'is_closed',
        'series_id',
        'series_name',
        'raw_data',
    ];

    protected $casts = [
        'available_date' => 'date',
        'start_at' => 'datetime',
        'capacity_total' => 'integer',
        'capacity_booked' => 'integer',
        'capacity_reserved' => 'integer',
        'unlimited_availability' => 'boolean',
        'sold_out' => 'boolean',
        'min_participants' => 'integer',
        'max_participants' => 'integer',
        'display_price' => 'decimal:2',
        'is_active' => 'boolean',
        'is_closed' => 'boolean',
        'raw_data' => 'array',
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

    public function categoryPrices(): HasMany
    {
        return $this->hasMany(AvailabilityCategoryPrice::class, 'availability_id')
            ->orderBy('category_code');
    }

    public function priceForCategory(string $categoryCode): ?AvailabilityCategoryPrice
    {
        return $this->categoryPrices
            ->firstWhere('category_code', strtoupper($categoryCode));
    }

    public function getRemainingCapacityAttribute(): int
    {
        if ($this->unlimited_availability) {
            return PHP_INT_MAX;
        }

        return max(0, $this->capacity_total - $this->capacity_booked - $this->capacity_reserved);
    }

    public function getIsBookableAttribute(): bool
    {
        return $this->is_active
            && ! $this->sold_out
            && ! $this->is_closed
            && ($this->unlimited_availability || $this->remaining_capacity > 0);
    }
}
