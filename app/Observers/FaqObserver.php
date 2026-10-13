<?php

namespace App\Observers;

use App\Models\Faq;
use Illuminate\Support\Facades\Cache;

class FaqObserver
{
    public function saved(Faq $faq): void
    {
        $this->clearCache($faq);
    }

    public function deleted(Faq $faq): void
    {
        $this->clearCache($faq);
    }

    private function clearCache(Faq $faq): void
    {
        Cache::forget('faqs:active');
        Cache::forget('faq:' . $faq->id);
    }
}
