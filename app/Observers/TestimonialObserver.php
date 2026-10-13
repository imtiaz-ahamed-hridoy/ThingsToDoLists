<?php

namespace App\Observers;

use App\Models\Testimonial;
use Illuminate\Support\Facades\Cache;

class TestimonialObserver
{
    public function saved(Testimonial $testimonial): void
    {
        $this->clearCache($testimonial);
    }

    public function deleted(Testimonial $testimonial): void
    {
        $this->clearCache($testimonial);
    }

    private function clearCache(Testimonial $testimonial): void
    {
        Cache::forget('testimonials:active');
        Cache::forget('testimonial:' . $testimonial->id);
    }
}
