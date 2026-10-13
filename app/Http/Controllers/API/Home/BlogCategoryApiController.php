<?php

namespace App\Http\Controllers\API\Home;

use App\Http\Controllers\Controller;
use App\Http\Resources\BlogCategoryResource;
use App\Repositories\Contracts\BlogCategoryRepositoryInterface;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BlogCategoryApiController extends Controller
{
    use ApiResponse;

    public function __construct(
        private BlogCategoryRepositoryInterface $blogCategoryRepository
    ) {}

    /**
     * List active blog categories sorted by name (cached).
     */
    public function index(Request $request)
    {
        $categories = Cache::remember('blog_categories:active', now()->addMinutes(20), function () {
            return $this->blogCategoryRepository->getActiveOrdered();
        });

        return $this->success(
            'Blog categories fetched successfully.',
            BlogCategoryResource::collection($categories)
        );
    }
}
