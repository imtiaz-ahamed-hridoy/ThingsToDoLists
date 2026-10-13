<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Support\ManualSelectionOrder;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BlogController extends Controller
{
    use ApiResponse;

    private function isAdmin(Request $request): bool
    {
        $role = strtolower((string) ($request->user()?->role ?? ''));
        return in_array($role, ['admin', 'administrator', 'super-admin', 'superadmin'], true);
    }

    public function index(Request $request)
    {
        if (! $this->isAdmin($request)) {
            return $this->error('Unauthorized access.', 403);
        }

        $perPage = (int) $request->input('per_page', 15);
        $query = BlogPost::orderBy('sort_order')->orderByDesc('published_at');
        $paginator = $query->paginate(max(1, min($perPage, 200)));

        return $this->success('Blog posts retrieved successfully.', $paginator);
    }

    public function store(Request $request)
    {
        if (! $this->isAdmin($request)) {
            return $this->error('Unauthorized access.', 403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:blog_posts,slug',
            'excerpt' => 'nullable|string',
            'content' => 'nullable|string',
            'featured_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'category_id' => 'nullable|exists:blog_categories,id',
            'published' => 'nullable|boolean',
            'published_at' => 'nullable|date',
            'sort_order' => 'nullable|integer'
        ]);

        $post = new BlogPost();
        $post->title = $request->input('title');
        $post->slug = $request->input('slug') ?: Str::slug($post->title) . '-' . time();
        $post->excerpt = $request->input('excerpt');
        $post->content = $request->input('content');
        $post->category_id = $request->input('category_id');
        $post->author_name = $request->input('author_name');
        $post->published = $request->boolean('published', false);
        $post->published_at = $request->input('published_at');
        $post->meta_title = $request->input('meta_title');
        $post->meta_description = $request->input('meta_description');
        $post->sort_order = $request->input('sort_order', (BlogPost::max('sort_order') ?? 0) + 1);

        if ($request->hasFile('featured_image')) {
            $post->featured_image = $request->file('featured_image')->store('cms/blogs/featured', 'public');
        }

        $post->save();

        // handle tags (ids or new comma separated names)
        $tagIds = [];
        $inputTags = $request->input('tags', []);
        if (is_array($inputTags)) {
            foreach ($inputTags as $t) {
                if (is_numeric($t)) $tagIds[] = (int) $t;
            }
        }
        $newTags = $request->input('tags_new');
        if (! empty($newTags) && is_string($newTags)) {
            foreach (array_map('trim', explode(',', $newTags)) as $name) {
                if ($name === '') continue;
                $slug = Str::slug($name);
                $tag = BlogTag::firstOrCreate(['slug' => $slug], ['name' => $name, 'slug' => $slug]);
                $tagIds[] = $tag->id;
            }
        }
        if (! empty($tagIds)) {
            $post->tags()->sync(ManualSelectionOrder::syncPayload($tagIds));
        }

        // related posts
        $related = $request->input('related_posts', []);
        if (is_array($related)) {
            $post->relatedPosts()->sync(ManualSelectionOrder::syncPayload($related));
        }

        return $this->success('Blog post created successfully.', $post);
    }

    public function show(Request $request, $id)
    {
        if (! $this->isAdmin($request)) {
            return $this->error('Unauthorized access.', 403);
        }

        $post = BlogPost::find($id);
        if (! $post) {
            return $this->error('Post not found', 404);
        }

        return $this->success('Blog post retrieved successfully.', $post);
    }

    public function update(Request $request, $id)
    {
        if (! $this->isAdmin($request)) {
            return $this->error('Unauthorized access.', 403);
        }

        $post = BlogPost::find($id);
        if (! $post) {
            return $this->error('Post not found', 404);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:blog_posts,slug,' . $post->id,
            'excerpt' => 'nullable|string',
            'content' => 'nullable|string',
            'featured_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'category_id' => 'nullable|exists:blog_categories,id',
            'published' => 'nullable|boolean',
            'published_at' => 'nullable|date',
            'sort_order' => 'nullable|integer'
        ]);

        $post->title = $request->input('title', $post->title);
        $post->slug = $request->input('slug', $post->getRawOriginal('slug'));
        $post->excerpt = $request->input('excerpt', $post->excerpt);
        $post->content = $request->input('content', $post->content);
        $post->category_id = $request->input('category_id', $post->category_id);
        $post->author_name = $request->input('author_name', $post->author_name);
        $post->published = $request->boolean('published', $post->published);
        $post->published_at = $request->input('published_at', $post->published_at);
        $post->meta_title = $request->input('meta_title', $post->meta_title);
        $post->meta_description = $request->input('meta_description', $post->meta_description);
        $post->sort_order = $request->input('sort_order', $post->sort_order);

        if ($request->hasFile('featured_image')) {
            $old = $post->getRawOriginal('featured_image');
            if ($old && Storage::disk('public')->exists($old)) {
                Storage::disk('public')->delete($old);
            }
            $post->featured_image = $request->file('featured_image')->store('cms/blogs/featured', 'public');
        }

        $post->save();

        // sync tags
        $tagIds = [];
        $inputTags = $request->input('tags', []);
        if (is_array($inputTags)) {
            foreach ($inputTags as $t) {
                if (is_numeric($t)) $tagIds[] = (int) $t;
            }
        }
        $newTags = $request->input('tags_new');
        if (! empty($newTags) && is_string($newTags)) {
            foreach (array_map('trim', explode(',', $newTags)) as $name) {
                if ($name === '') continue;
                $slug = Str::slug($name);
                $tag = BlogTag::firstOrCreate(['slug' => $slug], ['name' => $name, 'slug' => $slug]);
                $tagIds[] = $tag->id;
            }
        }
        $post->tags()->sync(ManualSelectionOrder::syncPayload($tagIds));

        // sync related posts
        $related = $request->input('related_posts', []);
        if (is_array($related)) {
            $post->relatedPosts()->sync(ManualSelectionOrder::syncPayload($related));
        }

        return $this->success('Blog post updated successfully.', $post);
    }

    public function destroy(Request $request, $id)
    {
        if (! $this->isAdmin($request)) {
            return $this->error('Unauthorized access.', 403);
        }

        $post = BlogPost::find($id);
        if (! $post) {
            return $this->error('Post not found', 404);
        }

        $old = $post->getRawOriginal('featured_image');
        if ($old && Storage::disk('public')->exists($old)) {
            Storage::disk('public')->delete($old);
        }

        $post->delete();

        return $this->success('Blog post deleted successfully.', []);
    }
}
