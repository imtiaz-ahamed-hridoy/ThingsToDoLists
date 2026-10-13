<?php

namespace App\Http\Controllers\API\Home;

use App\Http\Controllers\Controller;
use App\Http\Resources\BlogPostCardResource;
use App\Http\Resources\BlogPostDetailResource;
use App\Repositories\Contracts\BlogPostRepositoryInterface;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BlogApiController extends Controller
{
    use ApiResponse;

    public function __construct(
        private BlogPostRepositoryInterface $blogPostRepository
    ) {}

    /**
     * List published parent blog posts with optional filters (cached as resolved arrays).
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 12);
        $page = (int) $request->input('page', 1);
        $category = $request->input('category', '');
        $search = $request->input('search', '');

        $cacheKey = 'blogs:index:' . md5("{$perPage}|{$page}|{$category}|{$search}");

        $data = Cache::remember($cacheKey, now()->addMinutes(20), function () use ($perPage, $category, $search) {
            $query = $this->blogPostRepository->getParentsPublishedQuery(['category', 'tags', 'children'])
                ->orderBy('is_featured', 'desc')
                ->orderBy('sort_order')
                ->orderByDesc('published_at');

            if ($category) {
                $query->whereHas('category', function ($q) use ($category) {
                    $q->where('slug', $category);
                });
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('excerpt', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%")
                        ->orWhereHas('category', function ($qc) use ($search) {
                            $qc->where('name', 'LIKE', "%{$search}%");
                        })
                        ->orWhereHas('tags', function ($qt) use ($search) {
                            $qt->where('name', 'LIKE', "%{$search}%");
                        })
                        ->orWhereHas('children', function ($qchild) use ($search) {
                            $qchild->where('title', 'LIKE', "%{$search}%")
                                   ->orWhere('subtitle', 'LIKE', "%{$search}%")
                                   ->orWhere('excerpt', 'LIKE', "%{$search}%")
                                   ->orWhere('content', 'LIKE', "%{$search}%");
                        });
                });
            }

            $paginator = $query->paginate(max(1, min($perPage, 200)));

            return [
                'items' => BlogPostCardResource::collection($paginator->getCollection())->resolve(),
                'paginator' => $paginator,
            ];
        });

        return $this->pagination(
            'Blogs fetched successfully.',
            $data['items'],
            $data['paginator']
        );
    }

    /**
     * Search blog posts.
     */
    public function search(Request $request)
    {
        $term = $request->input('q', $request->input('search', ''));
        $perPage = (int) $request->input('per_page', 12);

        if (empty(trim($term))) {
            return $this->pagination('Blogs fetched successfully.', [], new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage));
        }

        $cacheKey = 'blog:search:' . md5("{$term}|{$perPage}");

        $data = Cache::remember($cacheKey, now()->addMinutes(20), function () use ($term, $perPage) {
            $paginator = $this->blogPostRepository->search($term, max(1, min($perPage, 200)));
            
            // Load relations for the resource
            $paginator->getCollection()->load(['category', 'tags', 'children']);

            return [
                'items' => BlogPostCardResource::collection($paginator->getCollection())->resolve(),
                'paginator' => $paginator,
            ];
        });

        return $this->pagination(
            'Blogs fetched successfully.',
            $data['items'],
            $data['paginator']
        );
    }

    /**
     * Get a single blog post by id with full details (articles + related) - cached.
     */
    public function show(Request $request, $id)
    {
        $cacheKey = 'blog:detail:' . $id;

        $response = Cache::remember($cacheKey, now()->addMinutes(20), function () use ($id) {
            $post = $this->blogPostRepository->findPublishedById((int) $id, ['category', 'tags', 'children']);

            if (!$post) {
                return null;
            }

            return (new BlogPostDetailResource($post))->resolve();
        });

        if (!$response) {
            return $this->error('Blog not found', 404);
        }

        return $this->success('Blog retrieved successfully.', $response);
    }

    /**
     * Get the featured blog post (cached as resolved array).
     */
    public function featured(Request $request)
    {
        $cacheKey = 'blog:featured';

        $response = Cache::remember($cacheKey, now()->addMinutes(20), function () {
            $post = $this->blogPostRepository->getFeatured();

            if ($post) {
                $post->load(['category', 'tags', 'children']);
                return (new BlogPostCardResource($post))->resolve();
            }

            // Fall back to most recent published post
            $post = $this->blogPostRepository->query()
                ->parents()
                ->published()
                ->with(['category', 'tags', 'children'])
                ->orderByDesc('published_at')
                ->first();

            if (!$post) {
                return null;
            }

            return (new BlogPostCardResource($post))->resolve();
        });

        if (!$response) {
            return $this->error('No blog posts found', 404);
        }

        return $this->success('Featured blog retrieved successfully.', $response);
    }

    /**
     * Get latest blog posts (cached as resolved arrays).
     */
    public function latest(Request $request)
    {
        $limit = min((int) $request->input('limit', 6), 20);

        $cacheKey = 'blog:latest:limit:' . $limit;

        $posts = Cache::remember($cacheKey, now()->addMinutes(20), function () use ($limit) {
            $items = $this->blogPostRepository->getLatestWithCategory($limit);
            return BlogPostCardResource::collection($items)->resolve();
        });

        return $this->success(
            'Latest blogs fetched successfully.',
            $posts
        );
    }
}
