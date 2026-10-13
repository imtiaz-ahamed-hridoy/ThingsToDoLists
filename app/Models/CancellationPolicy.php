<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CancellationPolicy extends Model
{
    protected $fillable = [
        'product_id',
        'provider',
        'title',
        'policy',
        'tax',
        'policyType',
        'is_cancellable',
        'cancellation_notes',
        'penalty_rules',
        'sort_order',
        'meta',
    ];

    protected $casts = [
        'is_cancellable' => 'boolean',
        'cancellation_notes' => 'array',
        'penalty_rules' => 'array',
        'meta' => 'array',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function getDetailsAttribute(): Collection
    {
        return collect($this->penalty_rules ?? [])->filter(fn ($row) => is_array($row))->values();
    }
}
