<?php

namespace App\Http\Controllers\Admin\Product;

use App\Http\Controllers\Controller;
use App\Services\GlobalTix\GlobalTixAuthService;
use App\Services\GlobalTix\GlobalTixProductService;
use App\Services\GlobalTix\GlobalTixProductSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class GlobalTixAdminController extends Controller
{
    protected GlobalTixAuthService $authService;
    protected GlobalTixProductService $productService;
    protected GlobalTixProductSyncService $syncService;

    public function __construct(
        GlobalTixAuthService $authService,
        GlobalTixProductService $productService,
        GlobalTixProductSyncService $syncService
    ) {
        $this->authService    = $authService;
        $this->productService = $productService;
        $this->syncService    = $syncService;
    }

    /**
     * GET /admin/globaltix/health
     *
     * Test GlobalTix API connectivity and authentication.
     * Returns diagnostic information about token acquisition.
     * Also loads current credential values for the settings form.
     */
    public function healthCheck()
    {
        $result = $this->authService->testConnection();

        // Pass current credential values to the view
        // Use config() instead of env() to ensure values work after php artisan config:cache.
        $credentials = [
            'mode'           => config('services.globaltix.mode', 'test'),
            'prod_base_url'  => config('services.globaltix.prod_base_url', ''),
            'prod_api_user'  => config('services.globaltix.prod_api_user', ''),
            'prod_api_key'   => config('services.globaltix.prod_api_key', ''),
            'prod_api_agent' => config('services.globaltix.prod_api_agent', ''),
            'test_base_url'  => config('services.globaltix.test_base_url', ''),
            'test_api_user'  => config('services.globaltix.test_api_user', ''),
            'test_api_key'   => config('services.globaltix.test_api_key', ''),
            'test_api_agent' => config('services.globaltix.test_api_agent', ''),
            'image_url'      => config('services.globaltix.image_url', ''),
            'webhook_secret' => config('services.globaltix.webhook_secret', ''),
        ];

        return view('admin.globaltix.health', [
            'result'      => $result,
            'credentials' => $credentials,
        ]);
    }

    /**
     * POST /admin/globaltix/save-credentials
     *
     * Save GlobalTix API credentials to the .env file.
     */
    public function saveCredentials(Request $request)
    {
        $request->validate([
            'mode'             => 'required|in:prod,test',
            'prod_base_url'    => 'required|url',
            'prod_api_user'    => 'required|string',
            'prod_api_key'     => 'required|string',
            'prod_api_agent'   => 'required|string',
            'test_base_url'    => 'required|url',
            'test_api_user'    => 'required|string',
            'test_api_key'     => 'required|string',
            'test_api_agent'   => 'required|string',
            'image_url'        => 'nullable|url',
            'webhook_secret'   => 'nullable|string',
        ]);

        $this->setEnv([
            'GLOBALTIX_MODE'                       => $request->mode,
            'GLOBALTIX_PROD_BASE_URL'              => $request->prod_base_url,
            'GLOBALTIX_PROD_API_USER'              => $request->prod_api_user,
            'GLOBALTIX_PROD_API_KEY'               => $request->prod_api_key,
            'GLOBALTIX_PROD_API_AGENT'             => $request->prod_api_agent,
            'GLOBALTIX_TEST_BASE_URL'              => $request->test_base_url,
            'GLOBALTIX_TEST_API_USER'              => $request->test_api_user,
            'GLOBALTIX_TEST_API_KEY'               => $request->test_api_key,
            'GLOBALTIX_TEST_API_AGENT'             => $request->test_api_agent,
            'GLOBALTIX_IMAGE_URL'                  => $request->image_url ?? '',
            'GLOBALTIX_WEBHOOK_SECRET'             => $request->webhook_secret ?? '',
        ]);

        // Clear config cache so new values take effect
        Artisan::call('config:clear');

        return redirect()->route('admin.globaltix.health')
            ->with('t-success', 'GlobalTix credentials updated and config cache cleared. Re-test the connection above.');
    }

    /**
     * Write key/value pairs to the .env file.
     */
    private function setEnv(array $data): void
    {
        $envPath = base_path('.env');
        $envContent = file_get_contents($envPath);

        foreach ($data as $key => $value) {
            $pattern = "/^{$key}=.*/m";
            $formattedValue = (str_contains($value, ' ') || str_contains($value, '$') || $value === '') ? '"' . $value . '"' : $value;

            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, "{$key}={$formattedValue}", $envContent);
            } else {
                $envContent .= "\n{$key}={$formattedValue}";
            }
        }

        file_put_contents($envPath, $envContent);
    }

    /**
     * GET /admin/globaltix/health-check (API-style for AJAX)
     *
     * Returns JSON health check result.
     */
    public function healthCheckApi()
    {
        $result = $this->authService->testConnection();

        return response()->json($result, $result['success'] ? 200 : 503);
    }

    /**
     * POST /admin/globaltix/clear-token
     *
     * Force clear the cached GlobalTix access token.
     * Next API call will acquire a fresh token.
     */
    public function clearToken()
    {
        $this->authService->clearTokenCache();

        return back()->with('success', 'GlobalTix token cache cleared. Next API call will acquire a fresh token.');
    }

    /**
     * POST /admin/globaltix/sync-products
     *
     * Trigger product sync for a given country code.
     */
    public function syncProducts(Request $request)
    {
        $request->validate([
            'country_code' => 'required|string|size:2',
            'page'         => 'nullable|integer|min:1',
        ]);

        $countryCode = strtoupper($request->input('country_code'));
        $page = (int) $request->input('page', 1);

        try {
            $response = $this->productService->getProductsByCountry($countryCode, $page);
            $products = $response['data']['products'] ?? [];

            if (empty($products)) {
                return back()->with('warning', "No products found for country: {$countryCode} (page {$page}).");
            }

            $ids = collect($products)->pluck('id')->toArray();
            $result = $this->syncService->importFromTempSelection($ids);

            $message = "Sync complete for {$countryCode} (page {$page}): "
                . "Imported {$result['imported']}, Updated {$result['updated']}, Skipped {$result['skipped']}";

            if (!empty($result['errors'])) {
                $message .= '. Errors: ' . implode('; ', array_slice($result['errors'], 0, 3));
            }

            return back()->with('success', $message);
        } catch (\Exception $e) {
            Log::error('GlobalTix Admin Sync Error', ['message' => $e->getMessage()]);
            return back()->with('error', 'Sync failed: ' . $e->getMessage());
        }
    }

    /**
     * POST /admin/globaltix/sync-single
     *
     * Import or update a single product by its GlobalTix Product ID.
     */
    public function syncSingleProduct(Request $request)
    {
        $request->validate([
            'product_api_id' => 'required|string',
        ]);

        $productApiId = $request->input('product_api_id');

        try {
            $result = $this->syncService->importOrUpdateByApiId('globaltix', $productApiId, true);

            if ($result['success']) {
                return back()->with('success', "Product [{$productApiId}] {$result['status']}: {$result['message']}");
            }

            return back()->with('error', "Product [{$productApiId}] failed: {$result['message']}");
        } catch (\Exception $e) {
            Log::error('GlobalTix Admin Single Sync Error', ['message' => $e->getMessage()]);
            return back()->with('error', 'Sync failed: ' . $e->getMessage());
        }
    }
}
