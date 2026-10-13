<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlogPostDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array for full blog detail.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'description_section' => $this->description_section,
            'featured_image' => $this->featured_image_url,
            'author_name' => $this->author_name,
            'minutes_to_read' => $this->minutes_to_read,
            'release_date' => $this->release_date,
            'published_at' => $this->published_at?->toIso8601String(),
            'category' => $this->whenLoaded('category', fn() => $this->category
                ? ['id' => $this->category->id, 'name' => $this->category->name, 'slug' => $this->category->slug]
                : null),
            'tags' => $this->whenLoaded('tags', fn() => $this->tags->map(fn($t) => [
                'id' => $t->id, 'name' => $t->name, 'slug' => $t->slug,
            ])->values()),
            'articles' => $this->whenLoaded('children', fn() => $this->children->map(fn($child) => [
                'id' => $child->id,
                'title' => $child->title,
                'subtitle' => $child->subtitle,
                'location' => $child->location,
                'artist' => null,
                'description' => $child->excerpt ?: strip_tags((string) $child->content),
                'image_url' => $child->featured_image_url,
                'sort_order' => $child->sort_order,
            ])->values()),
            'related_posts' => BlogPostCardResource::collection($this->related_posts),
        ];
    }
}
