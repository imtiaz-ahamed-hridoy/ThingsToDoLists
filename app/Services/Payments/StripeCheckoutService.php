<?php

namespace App\Services\Payments;

use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeCheckoutService
{
    private const ZERO_DECIMAL_CURRENCIES = [
        'BIF',
        'CLP',
        'DJF',
        'GNF',
        'JPY',
        'KMF',
        'KRW',
        'MGA',
        'PYG',
        'RWF',
        'UGX',
        'VND',
        'VUV',
        'XAF',
        'XOF',
        'XPF',
    ];

    private ?StripeClient $client = null;

    private string $webhookSecret;
    private string $secretKey;

    public function __construct()
    {
        $this->secretKey = (string) config('services.stripe.secret', '');
        $this->webhookSecret = (string) config('services.stripe.webhook', '');
    }

    public function createCheckoutSession(
        string $reference,
        float $amount,
        string $currency,
        string $successUrl,
        string $cancelUrl,
        array $metadata = [],
        string $productName = 'Booking'
    ): array {
        $currencyCode = strtolower($currency);
        $unitAmount = $this->toStripeUnitAmount($amount, $currencyCode);

        if ($unitAmount <= 0) {
            throw new \InvalidArgumentException('Stripe amount must be greater than zero.');
        }

        $normalizedMetadata = [];
        foreach (array_merge($metadata, ['reference' => $reference]) as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (!is_scalar($value)) {
                continue;
            }

            $normalizedMetadata[(string) $key] = (string) $value;
        }

        $session = $this->getClient()->checkout->sessions->create([
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => $currencyCode,
                    'unit_amount' => $unitAmount,
                    'product_data' => [
                        'name' => $productName !== '' ? $productName : 'Booking',
                    ],
                ],
            ]],
            'metadata' => $normalizedMetadata,
        ]);

        return $session->toArray();
    }

    public function createPaymentIntent(
        string $reference,
        float $amount,
        string $currency,
        array $metadata = []
    ): array {
        $currencyCode = strtolower($currency);
        $unitAmount = $this->toStripeUnitAmount($amount, $currencyCode);

        if ($unitAmount <= 0) {
            throw new \InvalidArgumentException('Stripe amount must be greater than zero.');
        }

        $normalizedMetadata = [];
        foreach (array_merge($metadata, ['reference' => $reference]) as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (!is_scalar($value)) {
                continue;
            }

            $normalizedMetadata[(string) $key] = (string) $value;
        }

        $intent = $this->getClient()->paymentIntents->create([
            'amount' => $unitAmount,
            'currency' => $currencyCode,
            'metadata' => $normalizedMetadata,
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
        ]);

        return $intent->toArray();
    }

    public function constructWebhookEvent(string $payload, ?string $signatureHeader): array
    {
        if ($this->webhookSecret === '') {
            throw new \RuntimeException('Stripe webhook secret is not configured.');
        }

        if (!$signatureHeader) {
            throw new \InvalidArgumentException('Missing Stripe-Signature header.');
        }

        try {
            $event = Webhook::constructEvent($payload, $signatureHeader, $this->webhookSecret);
            return $event->toArray();
        } catch (SignatureVerificationException|UnexpectedValueException $e) {
            throw new \RuntimeException('Invalid Stripe webhook signature.', previous: $e);
        }
    }

    public function refundPaymentIntent(
        string $paymentIntentId,
        float $amount,
        string $currency,
        ?string $reason = null,
        ?string $idempotencyKey = null
    ): array {
        if ($paymentIntentId === '') {
            throw new \InvalidArgumentException('Stripe payment_intent is required for refunds.');
        }

        $params = [
            'payment_intent' => $paymentIntentId,
            'amount' => $this->toStripeUnitAmount($amount, strtolower($currency)),
        ];

        if ($params['amount'] <= 0) {
            throw new \InvalidArgumentException('Stripe refund amount must be greater than zero.');
        }

        if ($reason) {
            $params['metadata'] = ['reason' => $reason];
        }

        $options = $idempotencyKey ? ['idempotency_key' => $idempotencyKey] : [];

        return $this->getClient()->refunds->create($params, $options)->toArray();
    }

    private function getClient(): StripeClient
    {
        if ($this->client !== null) {
            return $this->client;
        }

        if ($this->secretKey === '') {
            throw new \RuntimeException('Stripe secret key is not configured.');
        }

        $this->client = new StripeClient($this->secretKey);

        return $this->client;
    }

    private function toStripeUnitAmount(float $amount, string $currencyCode): int
    {
        $upperCurrency = strtoupper($currencyCode);

        if (in_array($upperCurrency, self::ZERO_DECIMAL_CURRENCIES, true)) {
            return (int) round($amount);
        }

        return (int) round($amount * 100);
    }
}
