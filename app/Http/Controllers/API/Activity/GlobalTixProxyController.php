<?php

namespace App\Http\Controllers\API\Activity;

use App\Http\Controllers\Controller;
use App\Services\GlobalTix\GlobalTixProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * GlobalTixProxyController
 *
 * A thin, transparent wrapper over official GlobalTix API endpoints.
 *
 * Design principle:
 *   - Each action validates `provider` = 'globaltix' to guard against misuse
 *   - Accepts the EXACT same query parameters as the official GlobalTix API
 *   - Returns the raw GlobalTix response JSON unchanged (no transformation)
 *   - Any GlobalTix API-level errors are passed through transparently
 *
 * Exposed endpoints (registered in routes/api/v1/public.php):
 *   GET /api/v1/globaltix/proxy/product-options  ->  GlobalTix GET /api/product/options
 */
class GlobalTixProxyController extends Controller
{
    public function __construct(
        private readonly GlobalTixProductService $productService
    ) {}

    /**
     * Proxy for GlobalTix: GET /api/product/options
     *
     * Official GlobalTix query params (all passed through as-is):
     *   - id             (required, integer) Product ID from /api/product/list response
     *   - isDynamicPrice (required, boolean) true for dynamic-priced products, false otherwise
     *   - lang           (optional, string)  ISO 639-1 language code. Defaults to 'en'
     *
     * This wrapper adds:
     *   - provider       (required, string)  Must be 'globaltix' — enforces single-provider scope
     *
     * @return JsonResponse Raw GlobalTix /api/product/options response
     */
    public function productOptions(Request $request): JsonResponse
    {
        $request->validate([
            'provider'       => 'required|string|in:globaltix',
            'id'             => 'required|integer|min:1',
            'isDynamicPrice' => 'required|in:true,false,1,0',
            'lang'           => 'nullable|string|min:2|max:5',
        ]);

        try {
            $currency = app()->bound('user.currency') ? app('user.currency') : strtoupper($request->query('currency', 'EUR'));
            $rawResponse = $this->productService->getProductOptions(
                productId:      (int) $request->input('id'),
                isDynamicPrice: (bool) $request->boolean('isDynamicPrice'),
                lang:           $request->input('lang', 'en'),
                currency:       $currency
            );

            // Return GlobalTix raw response unchanged — no transformation
            return response()->json($rawResponse);

        } catch (\Exception $e) {
            Log::error('GlobalTixProxy productOptions error', [
                'product_id'     => $request->input('id'),
                'isDynamicPrice' => $request->input('isDynamicPrice'),
                'lang'           => $request->input('lang'),
                'message'        => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Failed to fetch product options from GlobalTix.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
