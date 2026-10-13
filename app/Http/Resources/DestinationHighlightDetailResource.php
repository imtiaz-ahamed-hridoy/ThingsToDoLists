<?php

namespace App\Http\Resources;

use App\Traits\AppliesProductRequestState;
use Illuminate\Http\Resources\Json\JsonResource;

class DestinationHighlightDetailResource extends JsonResource
{
    use AppliesProductRequestState;

    /**
     * Transform the resource for the detail endpoint.
     * Includes all assigned products (ensure products relation is loaded with productImages).
     */
    public function toArray($request): array
    {
        $destination = $this->destination;
        $destinationName = $this->destination_type === 'city'
            ? $destination?->city_name
            : $destination?->country_name;

        return $this->applyProductRequestState([
            'id'          => $this->id,
            'name'        => $this->name,
            'destination' => $destinationName,
            'image'       => $this->image,
            'description' => $this->description,
            'products'    => $this->products->map(fn ($product) => $this->formatProduct($product))->values(),
        ]);
    }

    private function formatProduct($product): array
    {
        $thumbnail = $product->thumbnail;

        if (!$thumbnail && $product->relationLoaded('productImages') && $product->productImages->isNotEmpty()) {
            $thumbnail = $product->productImages->first()?->preview_url
                ?? $product->productImages->first()?->large_url
                ?? $product->productImages->first()?->original_url;
        }

        return [
            'id'          => $product->id,
            'title'       => $product->title,
            'slug'        => $product->slug,
            'image'       => $thumbnail,
            'rating'      => is_numeric($product->reviewRating) ? round((float) $product->reviewRating, 1) : null,
            'reviewCount' => (int) ($product->reviewCount ?? 0),
            'duration'    => $product->durationText,
            'city'        => $product->location?->city,
            'price'       => is_numeric($product->from_price) ? (float) $product->from_price : null,
            'from_price'     => is_numeric($product->from_price) ? (float) $product->from_price : null,
            'original_price' => is_numeric($product->original_price) ? (float) $product->original_price : null,
            'fromPrice'      => is_numeric($product->from_price) ? (float) $product->from_price : null,
            'originalPrice'  => is_numeric($product->original_price) ? (float) $product->original_price : null,
            'default_currency' => $product->default_currency ?? 'EUR',
            'currency'    => $product->default_currency ?? 'EUR',
            'is_wishlisted' => false,
        ];
    }
}
