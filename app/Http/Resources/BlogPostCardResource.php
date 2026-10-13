<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class BlogPostCardResource extends JsonResource
{
    /**
     * Transform the resource into an array for blog listing cards.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => Str::limit($this->excerpt, 150),
            'featured_image' => $this->featured_image_url,
            'release_date' => $this->release_date,
            'published_at' => $this->published_at?->toIso8601String(),
            'category' => $this->whenLoaded('category', fn() => $this->category
                ? ['id' => $this->category->id, 'name' => $this->category->name, 'slug' => $this->category->slug]
                : null),
            'minutes_to_read' => $this->minutes_to_read,
            'author_name' => $this->author_name,
        ];
    }
}
