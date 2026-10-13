<?php

namespace App\Http\Controllers\API\Faq;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\FaqRepositoryInterface;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Cache;

class FaqController extends Controller
{
    use ApiResponse;

    public function __construct(
        private FaqRepositoryInterface $faqRepository
    ) {}

    /**
     * List active FAQs with proper formatting and caching.
     */
    public function index()
    {
        $faqs = Cache::remember('faqs:active', now()->addMinutes(20), function () {
            return $this->faqRepository->getActive()->map(function ($faq) {
                return [
                    'id' => $faq->id,
                    'question' => $faq->question,
                    'answer' => $faq->answer,
                    'status' => (bool) $faq->status,
                ];
            });
        });

        return $this->success('Data retrieved successfully.', $faqs, 200);
    }

    public function show(string $id)
    {
        $cacheKey = 'faq:' . $id;

        $data = Cache::remember($cacheKey, now()->addMinutes(20), function () use ($id) {
            $faq = $this->faqRepository->find((int) $id);

            if (!$faq || !$faq->status) {
                return null;
            }

            return [
                'id' => $faq->id,
                'question' => $faq->question,
                'answer' => $faq->answer,
                'status' => (bool) $faq->status,
            ];
        });

        if (!$data) {
            return $this->error('Data not found', 404);
        }

        return $this->ok('Data retrieved successfully.', $data, 200);
    }
}
