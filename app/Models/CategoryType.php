<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoryType extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'status' => 'integer',
    ];

    public function categories()
    {
        return $this->hasMany(Categories::class, 'type', 'slug');
    }
}
