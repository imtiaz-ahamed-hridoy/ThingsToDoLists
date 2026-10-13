<?php

namespace App\Http\Resources;

use App\Services\Booking\BookingPolicyService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    use \App\Traits\ConvertsCurrency;
    public function toArray(Request $request): array
    {
        $originalCurrency = $this->currency ?? 'EUR';
        $currency = app()->bound('user.currency') ? app('user.currency') : $originalCurrency;

        $policy = app(BookingPolicyService::class)->evaluate($this->resource);
        $payment = $this->whenLoaded('payment');

        return [
            'id' => $this->id,
            'reference' => $this->booking_reference ?: $this->reference,
            'booking_reference' => $this->booking_reference,
            'confirmation_code' => $this->confirmation_code,
            'booking_id' => $this->booking_id,
            'product_booking_id' => $this->product_booking_id,
            'product_confirmation_code' => $this->product_confirmation_code,
            'provider_type' => $this->provider_type,
            'provider_status' => $this->provider_status,
            'status' => $this->status,
            'admin_status' => $this->admin_status,
            'booking_channel' => $this->booking_channel,
            'booking_source' => $this->booking_source,
            'customer' => [
                'name' => $this->customer_name,
                'email' => $this->customer_email,
                'phone' => $this->customer_phone,
            ],
            'amount' => [
                'total' => $this->total_price !== null ? $this->formatPrice((float) $this->total_price, $originalCurrency) : null,
                'currency' => $this->total_price !== null ? $currency : $this->currency,
            ],
            'payment' => [
                'gateway' => $this->payment_gateway ?: data_get($payment, 'payment_gateway'),
                'source' => $this->payment_source ?: data_get($payment, 'payment_source'),
                'stripe_session_id' => $this->stripe_session_id ?: data_get($payment, 'stripe_session_id'),
                'stripe_payment_intent_id' => $this->stripe_payment_intent_id ?: data_get($payment, 'stripe_payment_intent_id'),
                'paypal_order_id' => $this->paypal_order_id ?: data_get($payment, 'paypal_order_id'),
                'paypal_capture_id' => $this->paypal_capture_id ?: data_get($payment, 'paypal_capture_id'),
                'paid_at' => optional($this->paid_at ?: data_get($payment, 'paid_at'))->toIso8601String(),
                'confirmed_at' => optional($this->confirmed_at ?: data_get($payment, 'confirmed_at'))->toIso8601String(),
            ],
            'failure' => [
                'stage' => $this->failure_stage,
                'code' => $this->failure_code,
                'reason' => $this->failure_reason,
                'failed_at' => optional($this->failed_at)->toIso8601String(),
            ],
            'failure_stage' => $this->failure_stage,
            'failure_code' => $this->failure_code,
            'failure_reason' => $this->failure_reason,
            'cancellation' => [
                'can_cancel' => $policy['can_cancel'],
                'can_refund' => $policy['can_refund'],
                'reason' => $policy['reason'],
                'policy' => $policy['policy'],
                'cancelled_at' => optional($this->cancelled_at)->toIso8601String(),
                'cancelled_by_type' => $this->cancelled_by_type,
                'cancellation_reason' => $this->cancellation_reason,
            ],
            'refund' => [
                'status' => $this->refund_status,
                'amount' => $this->refund_amount !== null ? $this->formatPrice((float) $this->refund_amount, $this->refund_currency ?? 'EUR') : null,
                'currency' => $this->refund_amount !== null ? app()->bound('user.currency') ? app('user.currency') : ($this->refund_currency ?? 'EUR') : $this->refund_currency,
                'gateway' => $this->refund_gateway,
                'reference' => $this->refund_reference,
                'reason' => $this->refund_reason,
                'refunded_at' => optional($this->refunded_at)->toIso8601String(),
            ],
            'provider' => [
                'type' => $this->provider_type,
                'booking_id' => $this->booking_id,
                'product_booking_id' => $this->product_booking_id,
                'product_confirmation_code' => $this->product_confirmation_code,
                'provider_status' => $this->provider_status,
            ],
            'e_ticket_url' => $this->e_ticket_url,
            'globaltix_tickets' => $this->globaltix_tickets,
            'product' => $this->buildProductBlock($request, $policy),
            'details' => BookingInfoResource::collection($this->whenLoaded('details')),
            'events' => BookingInfoResource::collection($this->whenLoaded('events')),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }

    private function buildProductBlock(Request $request, array $policy): array
    {
        $product = $this->relationLoaded('product') ? $this->product : null;

        $providerType = $this->provider_type;
        if (!$providerType) {
            $firstDetail = $this->relationLoaded('details') ? $this->details->first() : $this->details()->first();
            $providerType = $firstDetail?->provider_type;
        }

        // Runtime fallback: if product_id is null (e.g. older bookings),
        // attempt to resolve via ticket type ID from child detail rows or raw data.
        if (!$product && !$this->product_id && in_array($providerType, ['globaltix', 'bokun'])) {
            $product = $this->resolveProductFromFallback($providerType);
        }

        $base = [
            'id'        => $this->product_id ?: ($product->id ?? $policy['product_id']),
            'vendor_id' => $this->vendor_id ?: ($product->vendor_id ?? $policy['vendor_id']),
            'title'     => $product?->title,
            'slug'      => $product?->slug,
            'thumbnail' => $product?->thumbnail
                ?? $product?->productImages?->first()?->preview_url
                ?? $product?->productImages?->first()?->large_url
                ?? $product?->productImages?->first()?->original_url,
            'review_rating' => $product ? (float) ($product->reviewRating ?? 0) : null,
            'review_count'  => $product ? (int) ($product->reviewCount ?? 0) : null,
            'reviews'       => [],
        ];

        if ($product) {
            $reviews = $product->relationLoaded('reviews')
                ? $product->reviews
                : $product->reviews()->where('status', 'Active')->with('users')->latest()->get();

            $base['reviews'] = ReviewResource::collection($reviews)->resolve($request);
        }

        return $base;
    }

    /**
     * Attempt to resolve the Product from child booking detail rows or raw provider data.
     */
    private function resolveProductFromFallback($providerType): ?\App\Models\Product
    {
        // 1. Try resolving via raw data (common for Bokun)
        $rawData = $this->provider_raw_data;
        if (is_array($rawData)) {
            $activityId = data_get($rawData, 'activityId') 
                ?? data_get($rawData, 'booking.activityBookings.0.activityId')
                ?? data_get($rawData, 'booking.productBookings.0.activityId')
                ?? data_get($rawData, 'booking.bookedProducts.0.activityId')
                ?? data_get($rawData, 'productId');

            if ($activityId) {
                $product = \App\Models\Product::where('product_api_id', (string) $activityId)
                    ->where(function ($q) use ($providerType) {
                        $q->where('provider', $providerType)->orWhere('platform', $providerType);
                    })->first();

                if ($product) {
                    $this->forceFill(['product_id' => $product->id, 'vendor_id' => $product->vendor_id])->saveQuietly();
                    $product->load(['reviews' => fn ($q) => $q->where('status', 'Active')->with('users')->latest(), 'productImages']);
                    return $product;
                }
            }
        }

        // 2. Try resolving via child details (common for GlobalTix)
        $details = $this->relationLoaded('details') ? $this->details : $this->details()->get();

        foreach ($details as $detail) {
            $ticketId = $detail->product_booking_id;
            if (empty($ticketId)) {
                continue;
            }

            // Try OptionPricingCategory first (ticket type ID)
            $category = \App\Models\OptionPricingCategory::where('provider', $providerType)
                ->where('external_id', (string) $ticketId)
                ->with('productOption.product')
                ->first();

            if ($category?->productOption?->product) {
                $product = $category->productOption->product;
                $this->forceFill(['product_id' => $product->id, 'vendor_id' => $product->vendor_id])->saveQuietly();
                $product->load(['reviews' => fn ($q) => $q->where('status', 'Active')->with('users')->latest(), 'productImages']);
                return $product;
            }

            // Try ProductOption (option-level external ID)
            $option = \App\Models\ProductOption::where('provider', $providerType)
                ->where('external_id', (string) $ticketId)
                ->with('product')
                ->first();

            if ($option?->product) {
                $product = $option->product;
                $this->forceFill(['product_id' => $product->id, 'vendor_id' => $product->vendor_id])->saveQuietly();
                $product->load(['reviews' => fn ($q) => $q->where('status', 'Active')->with('users')->latest(), 'productImages']);
                return $product;
            }

            // Try Product (product_api_id)
            $product = \App\Models\Product::where('provider', $providerType)
                ->where('product_api_id', (string) $ticketId)
                ->first();

            if ($product) {
                $this->forceFill(['product_id' => $product->id, 'vendor_id' => $product->vendor_id])->saveQuietly();
                $product->load(['reviews' => fn ($q) => $q->where('status', 'Active')->with('users')->latest(), 'productImages']);
                return $product;
            }
        }

        return null;
    }
}
