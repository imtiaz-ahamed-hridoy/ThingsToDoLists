<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Traits\HasMediaUrls;

class Vendor extends Model
{
    use HasMediaUrls;
    protected $fillable = [
        'name',
        'currency',
        'rating',
        'reviews',
        'link',
        'user_id',
        'business_type',
        'activities_count',
        'country',
        'locations',
        'vat_number',
        'tax_identification_number',
        'payment_method',
        'supplier_id',
        'preferred_language',
        // New columns
        'external_id',
        'provider',
        'description',
        'logo',
        'email',
        'phone',
        'address',
        'metadata',
    ];

    protected $casts = [
        'locations' => 'array',
        'metadata' => 'array',
        'rating' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Vendor $vendor) {
            if (! filled($vendor->name)) {
                $vendor->name = $vendor->user?->name
                    ?? $vendor->user?->name
                    ?? config('app.name', 'Vendor');
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'vendor_id');
    }

    public function invoices()
    {
        return $this->hasMany(VendorInvoice::class);
    }

    public function paymentConfirmations()
    {
        return $this->hasMany(VendorPaymentConfirmation::class);
    }

    protected function logo(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $this->resolveMediaUrl($value),
        );
    }
}

