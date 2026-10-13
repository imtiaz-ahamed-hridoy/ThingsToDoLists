<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Repositories\Contracts\BlogCategoryRepositoryInterface;
use App\Repositories\Contracts\BlogPostRepositoryInterface;
use App\Repositories\Contracts\BlogTagRepositoryInterface;
use App\Support\ManualSelectionOrder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class BlogPostService
{
    public function __construct(
        private ImageUploadService $imageUploadService,
        private BlogPostRepositoryInterface $blogPostRepository,
        private BlogCategoryRepositoryInterface $blogCategoryRepository,
        private BlogTagRepositoryInterface $blogTagRepository
    ) {}

    public function createPost(array $data, ?UploadedFile $featuredImage, array $tags = [], array $childrenData = []): BlogPost
    {
        // Un-feature other posts if this one is featured
        if (!empty($data['is_featured'])) {
            $this->blogPostRepository->unFeatureAll();
        }

        $post = new BlogPost();
        $post->parent_id = null;
        $post->title = $data['title'];
        $post->subtitle = $data['subtitle'] ?? null;
        $post->slug = $data['slug'] ?? Str::slug($data['title']) . '-' . time();
        $post->excerpt = $data['excerpt'] ?? null;
        $post->content = $data['content'] ?? null;
        $post->category_id = $this->resolveCategoryId($data);
        $post->author_name = $data['author_name'] ?? null;
        $post->published = !empty($data['published']);
        $post->published_at = $data['published_at'] ?? ($post->published ? now() : null);
        $post->meta_title = $data['meta_title'] ?? null;
        $post->meta_description = $data['meta_description'] ?? null;
        $post->sort_order = $data['sort_order'] ?? ($this->blogPostRepository->maxSortOrder() + 1);
        $post->is_featured = !empty($data['is_featured']);
        $post->minutes_to_read = $data['minutes_to_read'] ?? null;

        if ($featuredImage) {
            $post->featured_image = $this->imageUploadService->upload($featuredImage, 'cms/blogs/featured');
        }

        $post->save();

        $this->syncTags($post, $tags);
        $this->syncChildren($post, $childrenData);

        return $post;
    }

    public function updatePost(BlogPost $post, array $data, ?UploadedFile $featuredImage, array $tags = [], array $childrenData = []): BlogPost
    {
        // Un-feature other posts if this one is featured
        if (!empty($data['is_featured'])) {
            $this->blogPostRepository->unFeatureAllExcept($post->id);
        }

        $post->title = $data['title'] ?? $post->title;
        $post->subtitle = $data['subtitle'] ?? $post->subtitle;
        $post->slug = $data['slug'] ?? $post->slug;
        $post->excerpt = $data['excerpt'] ?? $post->excerpt;
        $post->content = $data['content'] ?? $post->content;
        $post->category_id = isset($data['category_id']) || isset($data['category_new'])
            ? $this->resolveCategoryId($data)
            : $post->category_id;
        $post->author_name = $data['author_name'] ?? $post->author_name;
        $post->published = isset($data['published']) ? !empty($data['published']) : $post->published;
        $post->published_at = $data['published_at'] ?? ($post->published && !$post->published_at ? now() : $post->published_at);
        $post->meta_title = $data['meta_title'] ?? $post->meta_title;
        $post->meta_description = $data['meta_description'] ?? $post->meta_description;
        $post->sort_order = $data['sort_order'] ?? $post->sort_order;
        $post->is_featured = isset($data['is_featured']) ? !empty($data['is_featured']) : $post->is_featured;
        $post->minutes_to_read = $data['minutes_to_read'] ?? $post->minutes_to_read;

        if ($featuredImage) {
            $oldImage = $post->getRawOriginal('featured_image');
            $this->imageUploadService->delete($oldImage);
            $post->featured_image = $this->imageUploadService->upload($featuredImage, 'cms/blogs/featured');
        }

        $post->save();

        $this->syncTags($post, $tags);
        $this->syncChildren($post, $childrenData, true);

        return $post;
    }

    public function deletePost(BlogPost $post): void
    {
        // Delete featured image
        $this->imageUploadService->delete($post->getRawOriginal('featured_image'));

        // Delete child post images and children
        foreach ($post->children as $child) {
            $this->imageUploadService->delete($child->getRawOriginal('featured_image'));
        }
        $post->children()->delete();
        $post->delete();
    }

    public function toggleStatus(BlogPost $post): BlogPost
    {
        $post->published = !$post->published;
        $post->save();
        return $post;
    }

    private function resolveCategoryId(array $data): ?int
    {
        if (!empty($data['category_new'])) {
            $name = trim($data['category_new']);
            if ($name !== '') {
                $slug = Str::slug($name);
                $category = $this->blogCategoryRepository->firstOrCreate(
                    ['slug' => $slug],
                    ['name' => $name, 'slug' => $slug, 'status' => true]
                );
                return $category->id;
            }
        }
        return $data['category_id'] ?? null;
    }

    public function syncTags(BlogPost $post, array $tagInput): void
    {
        $tagIds = [];
        foreach ($tagInput as $t) {
            if (is_numeric($t)) {
                $tagIds[] = (int) $t;
            } elseif (is_string($t) && !empty(trim($t))) {
                $slug = Str::slug(trim($t));
                $tag = $this->blogTagRepository->firstOrCreate(['slug' => $slug], ['name' => trim($t), 'slug' => $slug]);
                $tagIds[] = $tag->id;
            }
        }
        if (!empty($tagIds)) {
            $post->tags()->sync(ManualSelectionOrder::syncPayload($tagIds));
        }
    }

    public function syncChildren(BlogPost $post, array $childrenData, bool $isUpdate = false): void
    {
        if (empty($childrenData) || empty($childrenData['titles'])) {
            if ($isUpdate) {
                foreach ($post->children as $child) {
                    $this->imageUploadService->delete($child->getRawOriginal('featured_image'));
                }
                $post->children()->delete();
            }
            return;
        }

        $titles = $childrenData['titles'];
        $subtitles = $childrenData['subtitles'] ?? [];
        $descriptions = $childrenData['descriptions'] ?? [];
        $images = $childrenData['images'] ?? [];
        $clearImages = $childrenData['clear_images'] ?? [];
        $existingIds = $childrenData['existing_ids'] ?? [];

        $keepIds = [];

        foreach ($titles as $index => $title) {
            if (empty(trim((string) $title))) continue;

            $slug = Str::slug($title) . '-' . uniqid();
            $childId = $existingIds[$index] ?? null;

            if ($childId && $isUpdate) {
                $child = $this->blogPostRepository->find((int) $childId);
                if ($child && $child->parent_id === $post->id) {
                    $child->title = $title;
                    $child->subtitle = $subtitles[$index] ?? null;
                    $child->slug = $slug;
                    $child->excerpt = $descriptions[$index] ?? null;
                    $child->content = $descriptions[$index] ?? null;
                    $child->sort_order = $index;
                    $child->published = $post->published;

                    $hasNewImage = isset($images[$index]) && $images[$index] instanceof UploadedFile;
                    $shouldClear = !$hasNewImage && in_array((string)$index, $clearImages, true);

                    if ($shouldClear) {
                        $this->imageUploadService->delete($child->getRawOriginal('featured_image'));
                        $child->featured_image = null;
                    }

                    if ($hasNewImage) {
                        $this->imageUploadService->delete($child->getRawOriginal('featured_image'));
                        $child->featured_image = $this->imageUploadService->upload($images[$index], 'cms/blogs/children');
                    }

                    $child->save();
                    $keepIds[] = $child->id;
                }
            } else {
                $imagePath = null;
                if (isset($images[$index]) && $images[$index] instanceof UploadedFile) {
                    $imagePath = $this->imageUploadService->upload($images[$index], 'cms/blogs/children');
                }

                $child = new BlogPost();
                $child->parent_id = $post->id;
                $child->title = $title;
                $child->subtitle = $subtitles[$index] ?? null;
                $child->slug = $slug;
                $child->excerpt = $descriptions[$index] ?? null;
                $child->content = $descriptions[$index] ?? null;
                $child->featured_image = $imagePath;
                $child->sort_order = $index;
                $child->published = $post->published;
                $child->save();
                $keepIds[] = $child->id;
            }
        }

        // Delete removed children
        if ($isUpdate && !empty($keepIds)) {
            $removed = $post->children()->whereNotIn('id', $keepIds)->get();
            foreach ($removed as $child) {
                $this->imageUploadService->delete($child->getRawOriginal('featured_image'));
            }
            $post->children()->whereNotIn('id', $keepIds)->delete();
        }
    }
}
