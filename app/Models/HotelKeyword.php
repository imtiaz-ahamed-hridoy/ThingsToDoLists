<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\{Keyword, Product};

class HotelKeyword extends Model
{
    protected $table = 'hotel_keywords';

    protected $fillable = [
        'product_id',
        'keyword_id',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function keyword()
    {
        return $this->belongsTo(Keyword::class, 'keyword_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
