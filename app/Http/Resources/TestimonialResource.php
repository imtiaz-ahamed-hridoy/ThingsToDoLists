<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TestimonialResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'country' => $this->country,
            'country_icon' => $this->country_icon,
            'avater' => $this->avater,
            'image' => $this->image,
            'description' => $this->description,
            'footer_description' => $this->footer_description,
            'rating' => $this->rating,
            'status' => (bool) $this->status,
        ];
    }
}
