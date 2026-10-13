<?php

namespace App\Http\Controllers\API\DynamicPage;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\DynamicPageRepositoryInterface;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Cache;

class DynamicPageController extends Controller
{
    use ApiResponse;

    public function __construct(
        private DynamicPageRepositoryInterface $dynamicPageRepository
    ) {}

    /**
     * List active dynamic pages (cached, formatted with selected columns).
     */
    public function list()
    {
        $data = Cache::remember('dynamic_pages:active', now()->addMinutes(20), function () {
            return $this->dynamicPageRepository->query()
                ->where('status', 'Active')
                ->select(['id', 'page_title', 'page_slug', 'page_content'])
                ->orderBy('id')
                ->get()
                ->map(function ($page) {
                    return [
                        'id' => $page->id,
                        'page_title' => $page->page_title,
                        'page_slug' => $page->page_slug,
                        'page_content' => $page->page_content,
                    ];
                });
        });

        return $this->ok('Data retrieved successfully.', $data, 200);
    }

    public function show(string $id)
    {
        $cacheKey = 'dynamic_page:' . $id;

        $data = Cache::remember($cacheKey, now()->addMinutes(20), function () use ($id) {
            $page = $this->dynamicPageRepository->find((int) $id);

            if (!$page || $page->status !== 'Active') {
                return null;
            }

            return [
                'id' => $page->id,
                'page_title' => $page->page_title,
                'page_slug' => $page->page_slug,
                'page_content' => $page->page_content,
            ];
        });

        if (!$data) {
            return $this->error('Data not found', 404);
        }

        return $this->ok('Data retrieved successfully.', $data, 200);
    }
}
