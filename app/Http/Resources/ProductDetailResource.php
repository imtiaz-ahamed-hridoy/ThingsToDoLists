<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\ReviewResource;

class ProductDetailResource extends JsonResource
{
    use \App\Traits\ConvertsCurrency;
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $originalCurrency = (string) ($this->default_currency ?? $this->vendor?->currency ?? 'EUR');
        $provider = strtolower((string) ($this->provider ?? $this->platform ?? 'manual'));
        $isGlobalTix = ($provider === 'globaltix');

        // Apply local exchange-rate conversion for all providers, including GlobalTix.
        $currency = app()->bound('user.currency') ? app('user.currency') : $originalCurrency;
        $thumbnail = $this->thumbnail
            ?? $this->productImages->first()?->preview_url
            ?? $this->productImages->first()?->large_url
            ?? $this->productImages->first()?->original_url;

        $reviews = $this->relationLoaded('reviews')
            ? $this->reviews
            : $this->reviews()->where('status', 'Active')->with('users')->latest()->get();

        $reviewsData = ReviewResource::collection($reviews)->resolve($request);

        $isWishlisted = false;
        $userId = auth('sanctum')->id();
        $guestId = $request->header('X-Guest-Wishlist');

        if ($userId || $guestId) {
            if (!$request->attributes->has('wishlist_ids')) {
                $query = Wishlist::query();
                if ($userId) {
                    $query->where('user_id', $userId);
                } else {
                    $query->where('guest_id', $guestId)->whereNull('user_id');
                }
                $request->attributes->set('wishlist_ids', $query->pluck('product_id')->flip()->toArray());
            }

            $wishlistedIdsDetail = $request->attributes->get('wishlist_ids');
            $isWishlisted = isset($wishlistedIdsDetail[$this->id]);
        }

        $displayPrices = $this->resource instanceof Product ? $this->resource->getDisplayPrices() : ['from_price' => $this->from_price, 'original_price' => $this->original_price];
        $fromPriceVal = $this->normalizeNumeric($displayPrices['from_price'] ?? $this->from_price);
        $originalPriceVal = $this->normalizeNumeric($displayPrices['original_price'] ?? $this->original_price);

        return [
            'id' => $this->id,
            'product_api_id' => $this->product_api_id,
            'provider' => strtolower((string) ($this->provider ?? $this->platform ?? 'manual')),
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description ?? $this->full_description,
            'thumbnail' => $thumbnail,
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
                'type' => $this->category->type,
            ] : null),
            'status' => $this->status,
            'published' => (bool) $this->published,
            'duration' => $this->duration,
            'duration_type' => $this->durationType,
            'duration_text' => $this->durationText,
            'from_price'     => $this->formatPrice($fromPriceVal, $originalCurrency),
            'original_price' => $this->formatPrice($originalPriceVal, $originalCurrency),
            'fromPrice'      => $this->formatPrice($fromPriceVal, $originalCurrency),
            'originalPrice'  => $this->formatPrice($originalPriceVal, $originalCurrency),
            'price'          => $this->formatPrice($fromPriceVal, $originalCurrency),
            'currency' => $currency,
            'review_rating' => $this->normalizeNumeric($this->reviewRating),
            'review_count' => (int) ($this->reviewCount ?? 0),
            'is_wishlisted' => $isWishlisted,
            'reviews' => $reviewsData,
            'highlights' => $this->highlights ?? [],
            'inclusions' => $this->inclusions ?? $this->included ?? [],
            'exclusions' => $this->exclusions ?? $this->excluded ?? [],
            'know_before_you_go' => $this->know_before_you_go ?? [],
            'itinerary' => $this->itinerary ?? [],
            'what_to_expect' => $this->what_to_expect ?? [],
            'location' => $this->location ? [
                'city' => $this->location->city,
                'country' => $this->location->country,
                'country_code' => $this->location->countryCode,
                'latitude' => $this->normalizeNumeric($this->location->latitude),
                'longitude' => $this->normalizeNumeric($this->location->longitude),
                'coordinates' => $this->location->coordinates,
                'address' => $this->location->address,
            ] : null,
            'locations' => $this->locations->map(fn ($location) => [
                'id' => $location->id,
                'city' => $location->city,
                'country' => $location->country,
                'country_code' => $location->countryCode,
                'address' => $location->address,
                'latitude' => $this->normalizeNumeric($location->latitude),
                'longitude' => $this->normalizeNumeric($location->longitude),
                'coordinates' => $location->coordinates,
                'is_primary' => (bool) $location->is_primary,
            ])->values()->all(),
            'keywords' => $this->keywords->pluck('name')->values()->all(),
            'cancellation_policies' => $this->cancellationPolicies->map(fn ($policy) => [
                'title' => $policy->title,
                'policy' => $policy->policy,
                'policy_type' => $policy->policyType,
                'is_cancellable' => (bool) $policy->is_cancellable,
                'notes' => $policy->cancellation_notes ?? [],
                'penalty_rules' => $policy->penalty_rules ?? [],
            ])->values()->all(),
            'media' => [
                'photos' => $this->productImages->map(fn ($image) => [
                    'original' => $image->original_url,
                    'large' => $image->large_url,
                    'preview' => $image->preview_url,
                    'alt_text' => $image->alt_text,
                    'is_primary' => (bool) $image->is_primary,
                ])->values()->all(),
                'videos' => $this->productVideos->map(fn ($video) => [
                    'source_url' => $video->source_url,
                    'thumbnail_url' => $video->thumbnail_url,
                    'preview_url' => $video->preview_url,
                    'embed_html' => $video->embed_html,
                    'title' => $video->title,
                    'provider_name' => $video->provider_name,
                    'duration' => $video->duration,
                ])->values()->all(),
            ],
            'options' => $this->options->map(function ($option) use ($currency, $originalCurrency, $isGlobalTix) {
                return [
                    'id'                  => $option->id,
                    'external_id'         => $option->external_id,
                    'name'                => $option->name,
                    'description'         => $option->description,
                    'currency'            => $option->currency ?? $currency,
                    'ticket_format'       => $option->ticket_format,
                    'ticket_validity'     => $option->ticket_validity,
                    'defined_duration'    => $option->defined_duration,
                    'is_default'          => (bool) $option->is_default,
                    'is_dynamic_pricing'  => (bool) $option->is_dynamic_pricing,
                    'is_capacity'         => (bool) $option->is_capacity,
                    'is_cancellable'      => (bool) $option->is_cancellable,
                    'time_slot'           => $option->time_slot,
                    'visit_date'          => $option->visit_date,
                    'advance_booking'     => $option->advance_booking,
                    'questions'           => $option->questions,
                    'inclusions'          => $option->inclusions,
                    'exclusions'          => $option->exclusions,
                    'cancellation_policy' => $option->cancellation_policy,
                    'cancellation_notes'  => $option->cancellation_notes,
                    'publish_start'       => optional($option->publish_start)?->format('Y-m-d'),
                    'publish_end'         => optional($option->publish_end)?->format('Y-m-d'),
                    'redeem_start'        => optional($option->redeem_start)?->format('Y-m-d'),
                    'redeem_end'          => optional($option->redeem_end)?->format('Y-m-d'),
                    'pricing_categories'  => $option->pricingCategories->map(function ($category) use ($currency, $originalCurrency, $isGlobalTix) {
                        $catCurrency = $category->currency ?? $originalCurrency;
                        $normalizeCatPrice = function ($amount) use ($catCurrency) {
                            $val = $this->normalizeNumeric($amount);
                            if ($val === null) return null;
                            if ($val > 1000 && strtoupper((string) $catCurrency) === 'EUR') {
                                $converted = app(\App\Services\CurrencyService::class)->convert($val, 'IDR', 'EUR');
                                if ($converted !== null && $converted > 0 && $converted < 1000) {
                                    $val = $converted;
                                }
                            }
                            return $this->formatPrice($val, $catCurrency);
                        };

                        return [
                            'id'               => $category->id,
                            'external_id'      => $category->external_id,
                            'code'             => $category->category_code,
                            'label'            => $category->category_label,
                            'age_from'         => $category->age_from,
                            'age_to'           => $category->age_to,
                            'min_purchase_qty' => $category->min_purchase_qty,
                            'max_purchase_qty' => $category->max_purchase_qty,
                            'selling_price'    => $normalizeCatPrice($category->selling_price),
                            'nett_price'       => $normalizeCatPrice($category->nett_price),
                            'original_price'   => $normalizeCatPrice($category->original_price),
                            'recommended_price'=> $normalizeCatPrice($category->recommended_price),
                            'merchant_price'   => $normalizeCatPrice($category->merchant_price),
                            'currency'         => $currency,
                            'tiered_prices'    => $category->tieredPrices->map(fn ($tier) => [
                                'min_pax'    => $tier->min_pax,
                                'max_pax'    => $tier->max_pax,
                                'amount'     => $normalizeCatPrice($tier->amount),
                                'nett_amount'=> $normalizeCatPrice($tier->nett_amount),
                                'tax_amount' => $normalizeCatPrice($tier->tax_amount),
                                'currency'   => $currency,
                            ])->values()->all(),
                        ];
                    })->values()->all(),
                ];
            })->values()->all(),
            'prices' => $this->prices->map(function ($price) use ($currency, $originalCurrency) {
                $pCurrency = $price->currency ?? $originalCurrency;
                $normalizePrice = function ($amount) use ($pCurrency) {
                    $val = $this->normalizeNumeric($amount);
                    if ($val === null) return null;
                    if ($val > 1000 && strtoupper((string) $pCurrency) === 'EUR') {
                        $converted = app(\App\Services\CurrencyService::class)->convert($val, 'IDR', 'EUR');
                        if ($converted !== null && $converted > 0 && $converted < 1000) {
                            $val = $converted;
                        }
                    }
                    return $this->formatPrice($val, $pCurrency);
                };

                return [
                    'id'                  => $price->id,
                    'option_id'           => $price->product_option_id,
                    'pricing_category_id' => $price->pricing_category_id,
                    'provider'            => $price->provider,
                    'valid_from'          => optional($price->valid_from)?->format('Y-m-d'),
                    'valid_to'            => optional($price->valid_to)?->format('Y-m-d'),
                    'selling_price'       => $normalizePrice($price->selling_price),
                    'nett_price'          => $normalizePrice($price->nett_price),
                    'original_price'      => $normalizePrice($price->original_price),
                    'recommended_price'   => $normalizePrice($price->recommended_price),
                    'currency'            => $currency,
                    'is_default'          => (bool) $price->is_default,
                    'sort_order'          => $price->sort_order,
                ];
            })->values()->all(),
        ];
    }

    private function normalizeNumeric(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
