<?php

namespace App\Http\Controllers\API\Home;

use App\Http\Controllers\Controller;
use App\Http\Resources\TestimonialResource;
use App\Repositories\Contracts\TestimonialRepositoryInterface;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Cache;

class TestimonialApiController extends Controller
{
    use ApiResponse;

    public function __construct(
        private TestimonialRepositoryInterface $testimonialRepository
    ) {}

    /**
     * List active testimonials (cached as resolved arrays, 1 hour).
     */
    public function index()
    {
        try {
            $testimonials = Cache::remember('testimonials:active', now()->addMinutes(20), function () {
                $items = $this->testimonialRepository->getActive();
                return TestimonialResource::collection($items)->resolve();
            });

            return $this->success(
                'Testimonials fetched successfully.',
                $testimonials
            );
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    public function show(string $id)
    {
        try {
            $cacheKey = 'testimonial:' . $id;
            $testimonial = Cache::remember($cacheKey, now()->addMinutes(20), function () use ($id) {
                $item = $this->testimonialRepository->findOrFail((int) $id);
                return new TestimonialResource($item);
            });
            return $this->success(
                'Testimonial fetched successfully.',
                $testimonial
            );
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
}
