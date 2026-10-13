<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductListResource extends JsonResource
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

        $categoryRows = $this->options
            ->flatMap(fn ($option) => $option->pricingCategories)
            ->sortBy('sort_order')
            ->values();

        $displayPrices = $this->resource instanceof Product ? $this->resource->getDisplayPrices() : ['from_price' => $this->from_price, 'original_price' => $this->original_price];
        $fromPrice = $this->formatPrice(
            $this->normalizeNumeric($displayPrices['from_price'] ?? $this->from_price),
            $originalCurrency
        );
        $originalPrice = $this->formatPrice(
            $this->normalizeNumeric($displayPrices['original_price'] ?? $this->original_price),
            $originalCurrency
        );

        $thumbnail = $this->thumbnail
            ?? $this->productImages->first()?->preview_url
            ?? $this->productImages->first()?->large_url
            ?? $this->productImages->first()?->original_url;

        $categoriesPreview = $categoryRows
            ->take(3)
            ->map(function ($category) use ($currency, $originalCurrency) {
                $catCurrency = $category->currency ?? $originalCurrency;
                $val = $this->normalizeNumeric($category->selling_price);
                if ($val !== null && $val > 1000 && strtoupper((string) $catCurrency) === 'EUR') {
                    $converted = app(\App\Services\CurrencyService::class)->convert($val, 'IDR', 'EUR');
                    if ($converted !== null && $converted > 0 && $converted < 1000) {
                        $val = $converted;
                    }
                }
                return [
                    'code'          => $category->category_code,
                    'label'         => $category->category_label,
                    'selling_price' => $this->formatPrice($val, $catCurrency),
                    'currency'      => $currency,
                ];
            })
            ->values()
            ->all();

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

            $wishlistedIds = $request->attributes->get('wishlist_ids');
            $isWishlisted = isset($wishlistedIds[$this->id]);
        }

        return [
            'id' => $this->id,
            'product_id' => $this->id,
            'product_api_id' => $this->product_api_id,
            'provider' => strtolower((string) ($this->provider ?? $this->platform ?? 'manual')),
            'title' => $this->title,
            'slug' => $this->slug,
            'thumbnail' => $thumbnail,
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
                'type' => $this->category->type,
            ] : null),
            'city' => $this->location?->city,
            'country' => $this->location?->country,
            'review_rating' => $this->normalizeNumeric($this->reviewRating) ?? 0.0,
            'review_count' => (int) ($this->reviewCount ?? 0),
            'instant_confirmation' => (bool) $this->is_instant_confirmation,
            'is_wishlisted' => $isWishlisted,
            'currency' => $currency,
            'from_price' => $fromPrice,
            'original_price' => $originalPrice,
            'price' => $fromPrice,
            'fromPrice' => $fromPrice,
            'originalPrice' => $originalPrice,
            'formatted_from_price' => $fromPrice !== null ? $currency . ' ' . number_format($fromPrice, 2) : null,
            'categories_preview' => $categoriesPreview,
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
