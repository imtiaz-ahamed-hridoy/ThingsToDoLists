<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\{Cache, Http, Log};

class PaypalService
{
    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;

    public function __construct()
    {
        $this->baseUrl      = config('services.paypal.base_url');
        $this->clientId     = config('services.paypal.client_id');
        $this->clientSecret = config('services.paypal.client_secret');

        if ($this->baseUrl === '' || !str_starts_with($this->baseUrl, 'https://')) {
            throw new \RuntimeException('PayPal base_url is invalid. Example: https://sandbox.paypal.com');
        }
        if ($this->clientId === '' || $this->clientSecret === '') {
            throw new \RuntimeException('PayPal client credentials missing.');
        }
    }

    public function getAccessToken(): string
    {
        $cacheKey = 'paypal.access_token.' . sha1($this->baseUrl . '|' . $this->clientId);
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') return $cached;

        $url = $this->baseUrl . '/v1/oauth2/token';

        $response = Http::asForm()
            ->timeout(30)
            ->retry(2, 500)
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->acceptJson()
            ->post($url, ['grant_type' => 'client_credentials']);

        if (!$response->successful()) {
            $body = $response->json();

            Log::error('PayPal token failed', [
                'url' => $url,
                'status' => $response->status(),
                'paypal_error' => $body,
            ]);

            $err  = is_array($body) ? ($body['error'] ?? null) : null;
            $desc = is_array($body) ? ($body['error_description'] ?? $body['message'] ?? null) : null;

            if ($err === 'invalid_client') {
                throw new \RuntimeException(
                    'PayPal invalid_client: Sandbox/Live base URL and credentials do not match OR credentials are wrong.'
                );
            }

            throw new \RuntimeException('PayPal token request failed: HTTP ' . $response->status() . ($desc ? " - {$desc}" : ''));
        }

        $token = (string) ($response->json('access_token') ?? '');
        $ttl   = (int) ($response->json('expires_in') ?? 0);

        if ($token === '') throw new \RuntimeException('PayPal token response missing access_token');

        $seconds = $ttl > 120 ? $ttl - 60 : 300;
        Cache::put($cacheKey, $token, now()->addSeconds($seconds));

        return $token;
    }

    public function createOrder(array $payload, ?string $idempotencyKey = null): array
    {
        $token = $this->getAccessToken();
        $url = $this->baseUrl . '/v2/checkout/orders';

        $req = Http::withToken($token)
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 500)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Prefer' => 'return=representation',
            ]);

        if ($idempotencyKey) {
            $req = $req->withHeaders(['PayPal-Request-Id' => $idempotencyKey]);
        }

        $response = $req->post($url, $payload);

        if (!$response->successful()) {
            Log::error('PayPal create order failed', [
                'url' => $url,
                'status' => $response->status(),
                'paypal_error' => $response->json(),
            ]);
            throw new \RuntimeException('PayPal create order failed: HTTP ' . $response->status());
        }

        return (array) $response->json();
    }

    public function captureOrder(string $orderId, ?string $idempotencyKey = null): array
    {
        $token = $this->getAccessToken();
        $url = $this->baseUrl . "/v2/checkout/orders/{$orderId}/capture";

        $req = Http::withToken($token)
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 500)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Prefer' => 'return=representation',
            ]);

        if ($idempotencyKey) {
            $req = $req->withHeaders(['PayPal-Request-Id' => $idempotencyKey]);
        }

        $response = $req->post($url, (object)[]);

        if (!$response->successful()) {
            Log::error('PayPal capture failed', [
                'url' => $url,
                'orderId' => $orderId,
                'status' => $response->status(),
                'paypal_error' => $response->json(),
            ]);
            throw new \RuntimeException('PayPal capture failed: HTTP ' . $response->status());
        }

        return $responseData;
    }

    public function refundCapture(
        string $captureId,
        float $amount,
        string $currency,
        ?string $note = null,
        ?string $idempotencyKey = null
    ): array {
        if ($captureId === '') {
            throw new \InvalidArgumentException('PayPal capture id is required for refunds.');
        }

        if ($amount <= 0) {
            throw new \InvalidArgumentException('PayPal refund amount must be greater than zero.');
        }

        $token = $this->getAccessToken();
        $url = $this->baseUrl . "/v2/payments/captures/{$captureId}/refund";

        $payload = [
            'amount' => [
                'currency_code' => strtoupper($currency),
                'value' => number_format($amount, 2, '.', ''),
            ],
        ];

        if ($note) {
            $payload['note_to_payer'] = $note;
        }

        $req = Http::withToken($token)
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 500)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Prefer' => 'return=representation',
            ]);

        if ($idempotencyKey) {
            $req = $req->withHeaders(['PayPal-Request-Id' => $idempotencyKey]);
        }

        $response = $req->post($url, $payload);

        if (!$response->successful()) {
            Log::error('PayPal refund failed', [
                'url' => $url,
                'capture_id' => $captureId,
                'status' => $response->status(),
                'paypal_error' => $response->json(),
            ]);

            throw new \RuntimeException('PayPal refund failed: HTTP ' . $response->status());
        }

        return (array) $response->json();
    }

    public static function findApproveUrl(array $createOrderResponse): ?string
    {
        foreach (($createOrderResponse['links'] ?? []) as $link) {
            if (($link['rel'] ?? '') === 'approve') return $link['href'] ?? null;
        }
        return null;
    }

    public static function extractCaptureId(array $captureResponse): ?string
    {
        return $captureResponse['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;
    }

    public static function extractCaptureStatus(array $captureResponse): ?string
    {
        return $captureResponse['purchase_units'][0]['payments']['captures'][0]['status'] ?? null;
    }

    /**
     * Verify PayPal webhook signature for security
     */
    public function verifyWebhookSignature(string $webhookId, array $headers, string $rawBody): bool
    {
        try {
            $token = $this->getAccessToken();
            $url = $this->baseUrl . '/v1/notifications/verify-webhook-signature';

            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(10)
                ->post($url, [
                    'auth_algo'         => $headers['paypal-auth-algo'] ?? '',
                    'cert_url'          => $headers['paypal-cert-url'] ?? '',
                    'transmission_id'   => $headers['paypal-transmission-id'] ?? '',
                    'transmission_sig'  => $headers['paypal-transmission-sig'] ?? '',
                    'transmission_time' => $headers['paypal-transmission-time'] ?? '',
                    'webhook_id'        => $webhookId,
                    'webhook_event'     => json_decode($rawBody, true),
                ]);

            if (!$response->successful()) {
                Log::error('PayPal webhook verification failed', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);
                return false;
            }

            $verification = $response->json('verification_status');
            return $verification === 'SUCCESS';
        } catch (\Throwable $e) {
            Log::error('PayPal webhook verification error', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
