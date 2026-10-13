<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingQuestion extends Model
{
    protected $fillable = [
        'product_id',
        'personalData',
        'questionCode',
        'label',
        'help',
        'placeholder',
        'required',
        'dataType',
        'selectFromOptions',
        'selectMultiple',
        'options',
        'flags',
        'context',
        'pricingCategoryTriggerSelection',
        'rateTriggerSelection',
        'extraTriggerSelection',
        'requiredBeforeDeparture',
        'provider',
        'sort_order',
    ];

    protected $casts = [
        'personalData' => 'boolean',
        'required' => 'boolean',
        'selectFromOptions' => 'boolean',
        'selectMultiple' => 'boolean',
        'requiredBeforeDeparture' => 'boolean',
        'options' => 'array',
        'flags' => 'array',
        'sort_order' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
