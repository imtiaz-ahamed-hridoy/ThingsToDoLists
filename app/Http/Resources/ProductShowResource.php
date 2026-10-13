<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductShowResource extends JsonResource
{
    use \App\Traits\ConvertsCurrency;
    /**
     * Transform the resource into an array.
     * Unified card-ready format for product listing.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Normalize location coordinates
        $location = $this->location;
        $locationData = null;
        if ($location) {
            $coords = $location->coordinates;
            if (is_array($coords) && isset($coords['latitude'])) {
                $coords = [
                    'lat' => $coords['latitude'] ?? 0,
                    'lng' => $coords['longitude'] ?? 0,
                ];
            }
            $locationData = [
                'city'         => $location->city ?? null,
                'country'      => $location->country ?? null,
                'country_code' => $location->country_code ?? $location->countryCode ?? null,
                'coordinates'  => $coords,
            ];
        }

        // Price summary from consolidated DB fields
        $provider = strtolower((string) ($this->provider ?? $this->platform ?? 'manual'));
        $isGlobalTix = ($provider === 'globaltix');

        $originalCurrency = $this->default_currency ?? $this->price?->defaultCurrency ?? $this->vendor?->currency ?? 'EUR';

        // Apply local exchange-rate conversion for all providers, including GlobalTix.
        $priceCurrency = app()->bound('user.currency') ? app('user.currency') : $originalCurrency;

        // Extract synced features dynamically
        $syncFeatures = is_array($this->activityAttributes) ? $this->activityAttributes : [];

        $displayPrices = $this->resource instanceof Product ? $this->resource->getDisplayPrices() : ['from_price' => $this->from_price, 'original_price' => $this->original_price];
        $fromPriceVal = $displayPrices['from_price'] ?? $this->from_price;
        $originalPriceVal = $displayPrices['original_price'] ?? $this->original_price;

        $priceAmount = $this->formatPrice(
            $fromPriceVal ?? $this->price?->main_price ?? $this->price?->selling_price ?? $this->price?->original_price,
            $originalCurrency
        );
        $originalAmount = $this->formatPrice(
            $originalPriceVal,
            $originalCurrency
        );
        $nextAmount = $this->formatPrice(
            (float) ($syncFeatures['next_price'] ?? $priceAmount),
            $originalCurrency
        );

        $discountPercentage = 0;
        if ($originalAmount && $originalAmount > $priceAmount) {
            $discountPercentage = round((($originalAmount - $priceAmount) / $originalAmount) * 100);
        }

        $isWishlisted = false;
        $userId = auth('sanctum')->id();
        $guestId = $request->header('X-Guest-Wishlist');

        if ($userId || $guestId) {
            if (!$request->attributes->has('wishlist_ids')) {
                $query = \App\Models\Wishlist::query();
                if ($userId) {
                    $query->where('user_id', $userId);
                } else {
                    $query->where('guest_id', $guestId)->whereNull('user_id');
                }
                $request->attributes->set('wishlist_ids', $query->pluck('product_id')->flip()->toArray());
            }

            $wishlistedIdsShow = $request->attributes->get('wishlist_ids');
            $isWishlisted = isset($wishlistedIdsShow[$this->id]);
        }

        return [
            'id'                => $this->id,
            'product_api_id'    => $this->product_api_id,
            'provider'          => $this->provider ?? $this->platform,
            'is_wishlisted'     => $isWishlisted,

            // ─── Card Display Fields (matching frontend card UI) ───
            'title'             => $this->title,
            'slug'              => $this->slug,
            'description'       => $this->description,
            'thumbnail'         => $this->thumbnail,
            'status'            => $this->status,
            'published'         => $this->published,

            // Duration
            'duration_text'     => $this->durationText,
            'duration_type'     => $this->durationType ?? 'hours',

            // Explicit root level prices to match ProductListResource / Search API
            'from_price'        => $priceAmount,
            'original_price'    => $originalAmount,
            'fromPrice'         => $priceAmount,
            'originalPrice'     => $originalAmount,
            'price_raw'         => $priceAmount,

            // Classification
            'activity_type'     => $this->activityType,
            'booking_type'      => $this->bookingType,
            'product_category'  => $this->category?->name ?? null,

            // Reviews (For UI: 4.5 Stars, 15k layout)
            'review_rating'     => (float) ($this->reviewRating ?? 0),
            'review_count'      => (int) ($this->reviewCount ?? 0),

            // Features (UI badges dynamically extracted from synced attributes)
            'features' => [
                'instant_confirmation' => (bool) ($syncFeatures['instant_confirmation'] ?? true),
                'free_cancellation'    => (bool) ($syncFeatures['free_cancellation'] ?? false),
                'transfers_available'  => (bool) ($syncFeatures['transfers_available'] ?? false),
                'meals_included'       => (bool) ($syncFeatures['meals_included'] ?? false),
                'guided_tour'          => (bool) ($syncFeatures['guided_tour'] ?? false),
                'pickup_available'     => ($this->pickup_service === 'YES' || $this->pickup_service == 1 || !empty($syncFeatures['pickup_available'])),
            ],

            // ─── Price Summary (for card UI price display) ───
            'price' => [
                'amount'              => $priceAmount !== null ? (float) $priceAmount : null,
                'currency'            => $priceCurrency,
                'formatted'           => $priceAmount !== null ? "{$priceCurrency} " . number_format((float) $priceAmount, 2) : null,
                'from_price'          => $priceAmount,
                'original_price'      => $originalAmount,
                'fromPrice'           => $priceAmount,
                'originalPrice'       => $originalAmount,
                'original_amount'     => $originalAmount,
                'discount_percentage' => $discountPercentage,
                'has_discount'        => $discountPercentage > 0,
            ],

            // Legacy backward-compatibility overrides
            'next_default_price'         => $priceAmount,
            'next_default_price_as_text' => $priceAmount !== null ? "{$priceCurrency} " . number_format((float) $priceAmount, 2) : null,
            'next_default_price_money'   => $priceAmount !== null ? [
                'amount'   => $priceAmount,
                'currency' => $priceCurrency,
            ] : null,
            'original_default_price'     => $this->provider === 'globaltix' ? $originalAmount : null,

            // ─── Location (City display for UI badge) ───
            'location' => $locationData,

            // ─── Media (Photos array for UI Carousel) ───
            'images' => $this->images->map(fn ($img) => [
                'id' => $img->id,
                'original' => $img->original,
                'large' => $img->large,
                'preview' => $img->preview,
                'alt_text' => $img->alt_text,
                'sort_order' => $img->sort_order,
            ])->values()->all(),

            // ─── Vendor ───
            'vendor' => [
                'name'     => $this->vendor?->name,
                'currency' => $this->vendor?->currency ?? $priceCurrency,
            ],
        ];
    }
}
