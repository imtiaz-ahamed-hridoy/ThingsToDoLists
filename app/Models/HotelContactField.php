<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelContactField extends Model
{
    protected $fillable = [
        'product_id',
        'field',
        'required',
        'requiredBeforeDeparture',
        'sort_order',
    ];

    protected $casts = [
        'required' => 'boolean',
        'requiredBeforeDeparture' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
