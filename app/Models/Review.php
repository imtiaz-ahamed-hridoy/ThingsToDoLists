<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Review extends Model
{
    use HasFactory, LogsActivity;

    protected static function booted()
    {
        static::creating(function ($review) {
            $review->status = $review->status ?: 'Active';
        });

        static::updating(function ($review) {
            $review->status = $review->status ?: 'Active';
        });

        $updateProductRating = function (?int $productId) {
            if (! $productId) {
                return;
            }

            // Try to count only active/approved reviews first
            $query = static::where('product_id', $productId)
                ->where(function ($q) {
                    $q->where('status', 'Active')
                        ->orWhere('status', 'active')
                        ->orWhere('status', 1)
                        ->orWhere('status', '1')
                        ->orWhere('status', 'approved');
                });

            $count = $query->count();
            $avg = $query->avg('ratings');

            // Fallback to all reviews if no "active" ones found
            if ($count === 0) {
                $fallbackQuery = static::where('product_id', $productId);
                $count = $fallbackQuery->count();
                $avg = $fallbackQuery->avg('ratings');
            }

            Product::where('id', $productId)->update([
                'reviewRating' => $avg ? round((float) $avg, 1) : 0,
                'reviewCount' => $count,
            ]);
        };

        static::saved(function ($review) use ($updateProductRating) {
            if ($review->wasChanged('product_id')) {
                $updateProductRating((int) $review->getOriginal('product_id'));
            }

            $updateProductRating((int) $review->product_id);
        });

        static::deleted(fn ($review) => $updateProductRating((int) $review->product_id));
    }

    protected $fillable = [
        'user_id',
        'product_id',
        'ratings',
        'comment',
        'images',
        'status',
    ];

    protected $casts = [
        'images' => 'array',
        'created_at' => 'date:d-m-Y',
        'updated_at' => 'date:d-m-Y',
    ];

    public function users()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['ratings', 'comment'])
            ->logOnlyDirty()
            ->useLogName('review');
    }
}
