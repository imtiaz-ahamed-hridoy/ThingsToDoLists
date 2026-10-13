<?php

namespace App\Models;

use App\Traits\HasMediaUrls;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    use HasFactory, SoftDeletes, HasMediaUrls;

    protected $fillable = [
        'parent_id',
        'category_id',
        'title',
        'subtitle',
        'slug',
        'excerpt',
        'content',
        'featured_image',
        'author_name',
        'meta_title',
        'meta_description',
        'published',
        'published_at',
        'sort_order',
        'status',
        'is_featured',
        'minutes_to_read',
    ];

    protected $casts = [
        'published' => 'boolean',
        'published_at' => 'datetime',
        'sort_order' => 'integer',
        'is_featured' => 'boolean',
        'minutes_to_read' => 'integer',
    ];

    protected $appends = ['featured_image_url', 'release_date'];

    // ─── Scopes ─────────────────────────────────────────────

    /**
     * Scope to only parent posts (not child articles).
     */
    public function scopeParents($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope to only published posts.
     */
    public function scopePublished($query)
    {
        return $query->where('published', true);
    }

    // ─── Relationships ─────────────────────────────────────────

    public function parent()
    {
        return $this->belongsTo(BlogPost::class, 'parent_id');
    }

    /**
     * Child posts = sub-articles within this blog post.
     * Ordered by sort_order for consistent display.
     */
    public function children()
    {
        return $this->hasMany(BlogPost::class, 'parent_id')->orderBy('sort_order');
    }

    public function category()
    {
        return $this->belongsTo(BlogCategory::class, 'category_id');
    }

    public function tags()
    {
        return $this->belongsToMany(BlogTag::class, 'blog_post_tag', 'post_id', 'tag_id')
            ->withPivot('position')
            ->orderByPivot('position');
    }

    public function relatedPosts()
    {
        return $this->belongsToMany(BlogPost::class, 'blog_post_related', 'post_id', 'related_post_id')
            ->withPivot('position')
            ->orderByPivot('position');
    }

    // ─── Accessors ─────────────────────────────────────────────

    public function getFeaturedImageUrlAttribute(): ?string
    {
        return $this->resolveMediaUrl($this->featured_image);
    }

    public function getReleaseDateAttribute(): ?string
    {
        return $this->published_at?->format('F j, Y');
    }

    /**
     * Split content into paragraph sections (for description_section in API).
     * Handles both plain text double-newlines and Summernote <p> tags.
     */
    public function getDescriptionSectionAttribute(): array
    {
        if (empty($this->content)) {
            return [];
        }

        $html = $this->content;

        // Try <p> tag splitting first (Summernote output)
        if (str_contains($html, '<p>')) {
            preg_match_all('/<p[^>]*>(.*?)<\/p>/si', $html, $matches);
            if (!empty($matches[1])) {
                $paragraphs = array_map(function ($p) {
                    return trim(strip_tags($p));
                }, $matches[1]);
                $paragraphs = array_filter($paragraphs, fn($p) => $p !== '');
                if (!empty($paragraphs)) {
                    return array_values($paragraphs);
                }
            }
        }

        // Fallback: strip HTML and split by double newlines
        $text = strip_tags($html);
        $paragraphs = preg_split('/\n\s*\n/', $text);
        $paragraphs = array_map('trim', $paragraphs);
        $paragraphs = array_filter($paragraphs, fn($p) => !empty($p));

        return array_values($paragraphs) ?: [(string) Str::of($text)->limit(500)];
    }

    /**
     * Get related posts based on same category (excluding self and children).
     */
    public function getRelatedPostsAttribute()
    {
        if (!$this->category_id) {
            return collect();
        }

        return self::published()
            ->whereNull('parent_id')
            ->where('id', '!=', $this->id)
            ->where('category_id', $this->category_id)
            ->orderByDesc('published_at')
            ->take(6)
            ->get();
    }

    // ─── Public-facing arrays ──────────────────────────────────

    /**
     * Convert a child post (sub-article) into the article format
     * that the frontend BlogDetails component expects.
     */
    public function toArticleArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'location' => $this->location,
            'artist' => null,
            'description' => $this->excerpt ?: strip_tags((string) $this->content),
            'image_url' => $this->featured_image_url,
            'sort_order' => $this->sort_order,
        ];
    }

    /**
     * Full public array for blog detail page.
     */
    public function toPublicArray(): array
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
            'category' => $this->category
                ? ['id' => $this->category->id, 'name' => $this->category->name, 'slug' => $this->category->slug]
                : null,
            'tags' => $this->tags->map(fn($t) => ['id' => $t->id, 'name' => $t->name, 'slug' => $t->slug])->values(),
            'articles' => $this->children->map(fn($child) => $child->toArticleArray())->values(),
            'related_posts' => $this->related_posts->map(function ($p) {
                return [
                    'id' => $p->id,
                    'title' => $p->title,
                    'slug' => $p->slug,
                    'excerpt' => $p->excerpt,
                    'featured_image' => $p->featured_image_url,
                    'release_date' => $p->release_date,
                    'published_at' => $p->published_at?->toIso8601String(),
                ];
            })->values(),
        ];
    }

    /**
     * Minimal format for blog listing cards (BlogContainer, BlogSlider, FeatureBlog).
     */
    public function toCardArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => Str::limit($this->excerpt, 150),
            'featured_image' => $this->featured_image_url,
            'release_date' => $this->release_date,
            'published_at' => $this->published_at?->toIso8601String(),
            'category' => $this->category
                ? ['id' => $this->category->id, 'name' => $this->category->name, 'slug' => $this->category->slug]
                : null,
            'minutes_to_read' => $this->minutes_to_read,
            'author_name' => $this->author_name,
        ];
    }

    /**
     * Format for home page insight articles section.
     */
    public function toInsightCardArray(): array
    {
        return [
            'image'   => $this->featured_image_url,
            'title'   => $this->title,
            'date'    => $this->release_date ?? ($this->published_at?->format('F Y')),
            'minutes' => $this->minutes_to_read ? $this->minutes_to_read . ' min read' : null,
            'author'  => $this->author_name ?? 'Admin',
        ];
    }
}
