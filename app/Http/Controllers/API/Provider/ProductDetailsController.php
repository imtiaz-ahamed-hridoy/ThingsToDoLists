<?php

namespace App\Http\Controllers\API\Provider;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Provider\ProviderFactory;
use App\Traits\HasCurrency;
use App\Traits\HasLanguage;
use Illuminate\Support\Facades\Log;

class ProductDetailsController extends Controller
{
    use HasLanguage, HasCurrency;

    /**
     * POST /api/v1/product-details
     *
     * Fetch organized, human-readable product details in real-time from Bokun/GlobalTix.
     * Accepts product_api_id (required) + platform (required).
     * Optionally accepts product_id to resolve product_api_id & platform from DB.
     */
    public function __invoke(Request $request)
    {
        $request->validate([
            'product_id' => 'nullable|integer',
            'product_api_id' => 'nullable|string',
            'platform' => 'nullable|string|in:bokun,globaltix',
            'currency' => 'nullable|string',
        ]);

        try {
            // Resolve product_api_id and platform
            $productApiId = $request->input('product_api_id');
            $platform = $request->input('platform');

            if (!$productApiId) {
                if (!$request->input('product_id')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Either product_api_id + platform OR product_id is required.',
                    ], 422);
                }

                $product = Product::find($request->input('product_id'));

                if (!$product) {
                    return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
                }

                $productApiId = $product->product_api_id;
                $platform = $product->provider ?? $product->platform ?? 'bokun';

                if (!$productApiId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This product has no external API ID.',
                    ], 400);
                }
            }

            if (!$platform) {
                return response()->json([
                    'success' => false,
                    'message' => 'platform is required (bokun or globaltix).',
                ], 422);
            }

            $language = $this->resolveLanguage();
            if ($platform === 'bokun') {
                $language = strtoupper($language);
            } else {
                $language = strtolower($language);
            }
            
            $currency = $this->resolveCurrency();
            $providerService = ProviderFactory::make($platform);

            // Fetch organized product data from provider
            $data = $providerService->getFullProductData((string) $productApiId, $language, $currency);

            if (isset($data['status']) && $data['status'] === false) {
                return response()->json([
                    'success' => false,
                    'message' => $data['message'] ?? 'Failed to fetch product details from provider.',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'provider' => $platform,
                'product_api_id' => $productApiId,
                'data' => $data['data'] ?? $data,
            ]);
        } catch (\Exception $e) {
            Log::error('ProductDetailsController error', ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
