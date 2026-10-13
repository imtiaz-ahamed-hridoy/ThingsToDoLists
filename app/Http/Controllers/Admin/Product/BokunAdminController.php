<?php

namespace App\Http\Controllers\Admin\Product;

use App\Http\Controllers\Controller;
use App\Services\Bokun\BokunService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class BokunAdminController extends Controller
{
    protected BokunService $bokunService;

    public function __construct(BokunService $bokunService)
    {
        $this->bokunService = $bokunService;
    }

    /**
     * GET /admin/bokun/health
     *
     * Test Bokun API connectivity by fetching active activity IDs.
     */
    public function healthCheck()
    {
        $result = $this->testConnection();

        // Pass current credential values to the view
        // Use config() instead of env() so values work after php artisan optimize (config cache).
        $credentials = [
            'access_key' => config('services.bokun.access_key', ''),
            'secret_key' => config('services.bokun.secret_key', ''),
            'base_url'   => config('services.bokun.base_url', ''),
        ];

        return view('admin.bokun.health', [
            'result'      => $result,
            'credentials' => $credentials,
        ]);
    }

    /**
     * POST /admin/bokun/save-credentials
     *
     * Save Bokun API credentials to the .env file.
     */
    public function saveCredentials(Request $request)
    {
        $request->validate([
            'access_key' => 'required|string',
            'secret_key' => 'required|string',
            'base_url'   => 'required|url',
        ]);

        $this->setEnv([
            'BOKUN_ACCESS_KEY' => $request->access_key,
            'BOKUN_SECRET_KEY' => $request->secret_key,
            'BOKUN_API_URL'    => $request->base_url,
        ]);

        // Clear config cache so new values take effect
        Artisan::call('config:clear');

        return redirect()->route('admin.bokun.health')
            ->with('t-success', 'Bokun credentials updated and config cache cleared. Re-test the connection below.');
    }

    /**
     * Test the Bokun API connection.
     */
    private function testConnection(): array
    {
        try {
            $response = $this->bokunService->getActiveActivityIds();

            // Check if the response looks valid (has suppliers array)
            if (is_array($response) && isset($response['suppliers'])) {
                $supplierCount = count($response['suppliers']);
                $totalIds = 0;
                foreach ($response['suppliers'] as $supplier) {
                    $totalIds += count($supplier['activityIds'] ?? []);
                }

                return [
                    'success'       => true,
                    'message'       => "Bokun API connected successfully. Found {$supplierCount} supplier(s) with {$totalIds} active activities.",
                    'mode'          => config('services.bokun.base_url'),
                    'base_url'      => config('services.bokun.base_url'),
                    'suppliers'     => $supplierCount,
                    'active_ids'    => $totalIds,
                    'http_status'   => 200,
                ];
            }

            // Check if we got a raw success response
            if (is_array($response) && !empty($response)) {
                return [
                    'success'       => true,
                    'message'       => "Bokun API responded with a valid response.",
                    'mode'          => config('services.bokun.base_url'),
                    'base_url'      => config('services.bokun.base_url'),
                    'http_status'   => 200,
                    'response_keys' => array_keys($response),
                ];
            }

            return [
                'success'       => false,
                'message'       => 'Bokun API returned an unexpected response format.',
                'mode'          => config('services.bokun.base_url'),
                'base_url'      => config('services.bokun.base_url'),
                'http_status'   => 500,
            ];
        } catch (\Throwable $e) {
            return [
                'success'       => false,
                'message'       => 'Bokun API connection failed: ' . $e->getMessage(),
                'mode'          => config('services.bokun.base_url'),
                'base_url'      => config('services.bokun.base_url'),
                'http_status'   => 500,
            ];
        }
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
}
