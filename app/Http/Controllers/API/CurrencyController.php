<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\CurrencyRepositoryInterface;
use App\Services\CurrencyService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    use ApiResponse;

    protected CurrencyService $currencyService;

    public function __construct(
        CurrencyService $currencyService,
        private CurrencyRepositoryInterface $currencyRepository
    ) {
        $this->currencyService = $currencyService;
    }

    /**
     * GET /api/v1/public/currencies
     * Returns all active currencies for the frontend dropdown.
     */
    public function index()
    {
        $currencies = $this->currencyService->getActiveCurrencies();

        // Also tell the frontend what currency is currently active for this request
        $activeCurrency = app()->bound('user.currency') ? app('user.currency') : 'EUR';

        return $this->success('Currencies retrieved successfully.', [
            'active_currency' => $activeCurrency,
            'currencies'      => $currencies,
        ]);
    }

    /**
     * POST /api/v1/public/currencies/set
     * { "currency_code": "EUR" }
     *
     * LOGIC:
     *  - Validates that the code is an active currency in our system.
     *  - If the user is AUTHENTICATED  → saves to users.preferred_currency in DB (persists forever).
     *  - If the user is a GUEST        → does NOT save (no DB). Returns the code so the
     *    frontend stores it in localStorage and sends X-Currency on every request.
     *  - Always returns { "currency": "EUR", "saved_to_account": true/false }
     */
    public function setCurrency(Request $request)
    {
        $request->validate([
            'currency_code' => ['required', 'string', 'max:10'],
        ]);

        $currencyCode = strtoupper(trim($request->currency_code));

        // Validate that the currency is active in our system
        $currency = $this->currencyRepository->findByCode($currencyCode);
        $exists = $currency && $currency->is_active;
        if (!$exists) {
            return $this->error(
                "'{$currencyCode}' is not a supported currency. Use GET /currencies to see the full list.",
                422
            );
        }

        $savedToAccount = false;

        // Determine user: try Sanctum auth (works because the bearer token is resolved by guard)
        $user = auth('sanctum')->user();

        if ($user) {
            $user->preferred_currency = $currencyCode;
            $user->save();
            $savedToAccount = true;

            // Clear the per-token cache so the middleware picks up the new value immediately
            if ($token = $request->bearerToken()) {
                \Illuminate\Support\Facades\Cache::forget('user_currency_' . hash('sha256', $token));
            }
        }

        return $this->success(
            $savedToAccount
                ? "Currency set to {$currencyCode} and saved to your account."
                : "Currency set to {$currencyCode}. Store this in localStorage and send it as the X-Currency header on all future requests.",
            [
                'currency'         => $currencyCode,
                'saved_to_account' => $savedToAccount,
            ]
        );
    }
}
