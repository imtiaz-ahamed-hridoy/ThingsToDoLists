<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LanguageMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Extracts language strictly from the X-Language header,
     * defaulting to 'en' if not provided.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $language = null;

        // ── Priority 1: Explicit X-Language header ──────────────────────────────
        $fromHeader = $request->header('X-Language');
        if (!empty($fromHeader)) {
            $language = strtolower(trim($fromHeader));
        }

        // ── Priority 2: ?language= query parameter ───────────────────────────────
        if (!$language) {
            $fromQuery = $request->query('language');
            if (!empty($fromQuery)) {
                $language = strtolower(trim($fromQuery));
            }
        }

        // ── Priority 3: Default fallback ─────────────────────────────────────────
        if (!$language) {
            $language = 'en';
        }

        // Bind into service container — available everywhere (Resources, Controllers, etc.)
        app()->instance('user.language', $language);

        return $next($request);
    }
}
