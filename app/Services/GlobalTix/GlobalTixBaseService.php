<?php

namespace App\Services\GlobalTix;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class GlobalTixBaseService
{
    protected GlobalTixAuthService $authService;

    public function __construct(GlobalTixAuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Unified HTTP request helper with 401 retry and robust logging.
     *
     * @param string $method   GET|POST|PUT|DELETE
     * @param string $endpoint The relative API path (e.g. /api/product/list)
     * @param array  $data     Query params for GET, JSON body for others
     * @param bool   $isRetry  Internal flag to prevent infinite loops
     * @return array
     * @throws ConnectionException
     * @throws \Exception
     */
    protected function request(string $method, string $endpoint, array $data = [], bool $isRetry = false): array
    {
        $token = $this->authService->getAccessToken();

        if (!$token) {
            Log::error('GlobalTix API Client: Token acquisition failed.');
            throw new \Exception('Failed to obtain a valid GlobalTix access token.');
        }

        $baseUrl = rtrim($this->authService->getBaseUrl(), '/');
        // Standardize endpoint format
        $path = '/' . ltrim(str_replace($baseUrl, '', $endpoint), '/');
        $url = "{$baseUrl}{$path}";

        $headers = [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
            'Accept-Version' => (string) config('services.globaltix.accept_version', '1.0'),
            'x-api-agent'  => $this->authService->getApiAgent(),
            'Accept-Language' => app()->bound('user.language') ? app('user.language') : 'en',
        ];

        /** @var \Illuminate\Http\Client\Response $response */
        $request = Http::withoutVerifying()
            ->timeout(60)
            ->withToken($token)
            ->withHeaders($headers);

        $method = strtoupper($method);
        $response = match ($method) {
            'GET'    => $request->get($url, $data),
            'POST'   => $request->post($url, $data),
            'PUT'    => $request->put($url, $data),
            'DELETE' => $request->delete($url, $data),
            default  => throw new \Exception("Unsupported HTTP method: {$method}"),
        };

        if (!$response->successful()) {
            $status = $response->status();                // Auto-retry once on 401 by clearing cache and re-authenticating
            if ($status === 401 && !$isRetry) {
                $this->authService->clearTokenCache();
                return $this->request($method, $path, $data, true);
            }

            $body = $response->body();
            Log::error("GlobalTix API failure [HTTP {$status}]", [
                'url'      => $url,
                'method'   => $method,
                'response' => $body,
                'payload'  => $data,
            ]);

            // Extract the most meaningful error message from GlobalTix error responses
            $jsonResponse = $response->json();
            $message = $jsonResponse['message']
                ?? $jsonResponse['error']['message']
                ?? $jsonResponse['error']
                ?? $body
                ?: "HTTP Standard Error [{$status}]";

            if (is_array($message)) {
                $message = json_encode($message);
            }

            throw new \Exception("GlobalTix API error [{$status}]: {$message}");
        }

        $responseData = $response->json();
        if (is_null($responseData)) {
            Log::error("GlobalTix API returned non-JSON response for [{$path}]", ['body' => $response->body()]);
            throw new \Exception("Empty or malformed JSON from GlobalTix API.");
        }

        return $responseData;
    }
}
