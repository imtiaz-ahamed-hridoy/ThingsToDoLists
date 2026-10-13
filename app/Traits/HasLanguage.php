<?php

namespace App\Traits;

trait HasLanguage
{
    /**
     * Resolve the requested language.
     * Uses the X-Language header processed by LanguageMiddleware.
     * Default fallback is 'en'.
     */
    protected function resolveLanguage(): string
    {
        if (app()->bound('user.language')) {
            return app('user.language');
        }
        return 'en';
    }
}
