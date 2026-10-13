<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Cache;

class CurrencyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * PRIORITY ORDER:
     *   1. X-Currency header  (explicit per-request override — always wins)
     *   2. ?currency= query string
     *   3. Authenticated user's saved preferred_currency in DB
     *   4. Default → EUR
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $currency = null;

        // ── Priority 1: Explicit X-Currency header ──────────────────────────────
        $fromHeader = $request->header('X-Currency');
        if (!empty($fromHeader)) {
            $currency = strtoupper(trim($fromHeader));
        }

        // ── Priority 2: ?currency= query parameter ───────────────────────────────
        if (!$currency) {
            $fromQuery = $request->query('currency');
            if (!empty($fromQuery)) {
                $currency = strtoupper(trim($fromQuery));
            }
        }

        // ── Priority 3: Authenticated user's saved preference ────────────────────
        // We resolve the user MANUALLY from the Bearer token so we don't depend
        // on auth:sanctum middleware having run already.
        if (!$currency) {
            $currency = $this->resolveUserPreferredCurrency($request);
        }

        // ── Priority 4: Default fallback ─────────────────────────────────────────
        if (!$currency) {
            $currency = 'EUR';
        }

        // Bind into service container — available everywhere (Resources, Controllers, etc.)
        app()->instance('user.currency', $currency);

        return $next($request);
    }

    /**
     * Resolve the authenticated user's preferred currency directly from the
     * Bearer token without relying on auth:sanctum middleware order.
     */
    private function resolveUserPreferredCurrency(Request $request): ?string
    {
        $token = $request->bearerToken();
        if (!$token) {
            return null;
        }

        // Cache the result per token for this request lifecycle (avoid DB hit on every price)
        $cacheKey = 'user_currency_' . hash('sha256', $token);

        return Cache::remember($cacheKey, 300, function () use ($token) {
            // Sanctum stores tokens as "<id>|<plaintext>"
            $parts = explode('|', $token, 2);
            $id    = count($parts) === 2 ? $parts[0] : null;

            if (!$id || !is_numeric($id)) {
                return null;
            }

            $accessToken = PersonalAccessToken::find((int) $id);

            if (!$accessToken) {
                return null;
            }

            // Verify the token hash matches (security check)
            if (!hash_equals($accessToken->token, hash('sha256', $parts[1]))) {
                return null;
            }

            $user = $accessToken->tokenable;

            return !empty($user->preferred_currency)
                ? strtoupper($user->preferred_currency)
                : null;
        });
    }
}
