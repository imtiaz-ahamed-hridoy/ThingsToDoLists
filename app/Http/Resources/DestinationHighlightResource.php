<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DestinationHighlightResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * Used for the index (list) endpoint.
     * Prefer withCount('products') on the query to avoid N+1.
     */
    public function toArray($request): array
    {
        $destination = $this->destination;
        $destinationName = $this->destination_type === 'city'
            ? $destination?->city_name
            : $destination?->country_name;

        // Use withCount('products') when querying, fallback to relationship count
        $totalProducts = $this->products_count
            ?? ($this->relationLoaded('products') ? $this->products->count() : 0);

        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'destination'    => $destinationName,
            'image'          => $this->image,
            'description'    => $this->description,
            'products_count' => (int) $totalProducts,
        ];
    }
}
