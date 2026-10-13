<?php

namespace App\Services\GlobalTix;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GlobalTixAuthService
{
    protected string $mode;
    protected string $baseUrl;
    protected string $apiUser;
    protected string $apiKey;
    protected string $apiAgent;
    protected string $imageUrl;

    public function __construct()
    {
        $this->mode     = trim((string) config('services.globaltix.mode', 'prod'));
        $this->baseUrl  = rtrim(trim((string) config('services.globaltix.base_url', 'https://sg-api.globaltix.com')), '/');
        $this->apiUser  = trim((string) config('services.globaltix.api_user', ''));
        $this->apiKey   = trim((string) config('services.globaltix.api_key', ''));
        // Keep exact value from config because GlobalTix treats agent/key pairing as strict.
        $this->apiAgent = trim((string) config('services.globaltix.api_agent', ''));
        $this->imageUrl = trim((string) config('services.globaltix.image_url', 'https://product-image.globaltix.com/live-gtImage/'));
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getImageUrl(): string
    {
        return $this->imageUrl;
    }

    public function getApiAgent(): string
    {
        return $this->apiAgent;
    }

    /**
     * Get the active credentials array (used by ProductService for headers).
     */
    public function getActiveCredentials(): array
    {
        return [
            'mode'      => $this->mode,
            'base_url'  => $this->baseUrl,
            'api_user'  => $this->apiUser,
            'api_key'   => $this->apiKey,
            'api_agent' => $this->apiAgent,
            'image_url' => $this->imageUrl,
        ];
    }

    /**
     * Clear the cached access token (useful when token is rejected mid-session).
     */
    public function clearTokenCache(): void
    {
        Cache::forget($this->getCacheKey());
    }

    protected function getCacheKey(): string
    {
        return 'globaltix_access_token_' . md5($this->baseUrl . '|' . $this->apiUser);
    }

    /**
     * Get access token using the official /api/auth/authorize endpoint.
     * Returns a cached token if one exists, otherwise authenticates fresh.
     * Token expires every 24h (86400s) per GlobalTix docs.
     *
     * @throws \Exception
     */
    public function getAccessToken(): string
    {
        if (empty($this->apiUser) || empty($this->apiKey) || empty($this->apiAgent)) {
            throw new \Exception(
                'GlobalTix credentials not fully configured. ' .
                "Mode: {$this->mode}. Check GLOBALTIX_MODE and the matching GLOBALTIX_{MODE}_API_USER/API_KEY/API_AGENT values."
            );
        }

        $cacheKey = $this->getCacheKey();

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $responseInfo = $this->authenticate();

        if (empty($responseInfo) || empty($responseInfo['token'])) {
            $reason = 'Unknown error';
            if (is_array($responseInfo) && isset($responseInfo['status'])) {
                $status = $responseInfo['status'];
                $body = $responseInfo['body'] ?? 'No body';
                $reason = "HTTP $status | Body: $body";
            }
            throw new \Exception("Failed to obtain GlobalTix access token. API Response: {$reason}");
        }

        return $responseInfo['token'];
    }

    /**
     * Test connectivity to GlobalTix API by performing a fresh authentication.
     * Returns diagnostic information for health checks.
     *
     * @return array{success: bool, message: string, token_preview?: string, roles?: array, expires_in?: int}
     */
    public function testConnection(): array
    {
        try {
            if (empty($this->apiUser) || empty($this->apiKey) || empty($this->apiAgent)) {
                return [
                    'success' => false,
                    'message' => 'GlobalTix credentials not configured. Check .env file.',
                    'config'  => [
                        'mode'      => $this->mode,
                        'base_url'  => $this->baseUrl,
                        'api_user'  => $this->apiUser ? '***configured***' : '⚠ MISSING',
                        'api_key'   => $this->apiKey ? '***configured***' : '⚠ MISSING',
                        'api_agent' => $this->apiAgent ?: '⚠ MISSING',
                    ],
                ];
            }

            // Force fresh authentication (bypass cache)
            $url = "{$this->baseUrl}/api/auth/authorize";

            $response = Http::withoutVerifying()
                ->asJson()
                ->timeout(30)
                ->withHeaders([
                    'x-api-key'      => $this->apiAgent . '/' . $this->apiKey,
                    'x-api-agent'    => $this->apiAgent,
                    'Accept'         => 'application/json',
                    'User-Agent'     => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                ])
                ->post($url, [
                    'username' => $this->apiUser,
                ]);


            if ($response->successful()) {
                $data = $response->json();
                $token = $data['data']['accessToken'] ?? null;
                $roles = $data['data']['roles'] ?? [];

                if ($token) {
                    return [
                        'success'       => true,
                        'message'       => 'GlobalTix API connection successful.',
                        'token_preview' => substr($token, 0, 20) . '...',
                        'roles'         => $roles,
                        'expires_in'    => $data['data']['expiration'] ?? 86400,
                        'mode'          => $this->mode,
                        'base_url'      => $this->baseUrl,
                        'api_agent'     => $this->apiAgent,
                    ];
                }

                return [
                    'success' => false,
                    'message' => 'Auth succeeded but no token in response.',
                    'response_keys' => array_keys($data['data'] ?? []),
                ];
            }

            return [
                'success'     => false,
                'message'     => "Auth failed with HTTP {$response->status()}.",
                'http_status' => $response->status(),
                'body'        => $response->body(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Authenticate with the official GlobalTix endpoint.
     *
     * Official GlobalTix API docs (Production):
     *   POST https://sg-api.globaltix.com/api/auth/authorize
     *
     *   Headers:
     *     x-api-key:    {x-api-agent}/{API_Key}   (case-sensitive!)
     *     x-api-agent:  {x-api-agent}             (case-sensitive!)
     *     Content-Type: application/json
     *
     *   Body (JSON):
     *     { "username": "<apiUser email>" }
     *
     *   Response 200:
     *     {
     *       "data": {
     *         "roles": [...],
     *         "tokenType": "Bearer",
     *         "accessToken": "eyJ...",
     *         "expiration": 86400
     *       }
     *     }
     */
    protected function authenticate(): ?array
    {
        $cacheKey = $this->getCacheKey();
        $url = "{$this->baseUrl}/api/auth/authorize";

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withoutVerifying()
                ->asJson()
                ->timeout(30)
                ->withHeaders([
                    'x-api-key'      => $this->apiAgent . '/' . $this->apiKey,
                    'x-api-agent'    => $this->apiAgent,
                    'Accept'         => 'application/json',
                    'User-Agent'     => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                ])
                ->post($url, [
                    'username' => $this->apiUser,
                ]);



            if ($response->successful()) {
                $data = $response->json();
                $token = $data['data']['accessToken'] ?? null;
                $expiration = (int) ($data['data']['expiration'] ?? 86400);

                if ($token) {
                    // Cache token with 5-minute safety margin before expiration
                    Cache::put($cacheKey, $token, now()->addSeconds($expiration - 300));

                    return ['token' => $token];
                }

                Log::error('GlobalTix: Auth response successful but no accessToken found.', [
                    'response_keys' => array_keys($data['data'] ?? []),
                ]);
            } else {
                Log::error('GlobalTix: Authentication failed.', [
                    'http_status' => $response->status(),
                    'body'        => $response->body(),
                ]);
            }

            return [
                'token'  => null,
                'status' => $response->status(),
                'body'   => $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('GlobalTix: Authentication exception.', [
                'message' => $e->getMessage(),
            ]);

            return [
                'token'  => null,
                'status' => 500,
                'body'   => $e->getMessage(),
            ];
        }
    }
}
