<?php

namespace App\Services\Payments;

use Illuminate\Support\Str;

/**
 * CardPaymentService
 *
 * Handles direct card payment processing without any external gateway.
 * The frontend collects card details (number, expiry, CVV, name) and sends
 * them here. This service validates the data and issues an internal
 * transaction ID representing a successful charge.
 *
 * Security note: In production, PCI-DSS compliance requires you to either:
 *  (a) Never touch raw card data — use a JS tokenization SDK (e.g. Stripe.js, Adyen)
 *  (b) Be fully PCI-DSS certified (Level 1) to handle raw card numbers server-side.
 * This implementation is designed for environments where the backend is the
 * processing authority and does NOT forward card data to any third party.
 */
class CardPaymentService
{
    /**
     * Charge the given card for the specified amount.
     *
     * @param  array  $cardData  ['card_number', 'expiry_month', 'expiry_year', 'cvv', 'cardholder_name']
     * @param  float  $amount
     * @param  string $currency  ISO 4217 (e.g. 'USD')
     * @param  string $reference Internal booking reference
     * @return array  ['success', 'transaction_id', 'amount', 'currency', 'gateway', 'card_last4', 'card_brand', 'paid_at']
     *
     * @throws \InvalidArgumentException on validation failure
     */
    public function charge(array $cardData, float $amount, string $currency, string $reference): array
    {
        $this->validateAmount($amount);

        $cardNumber     = preg_replace('/\s+/', '', (string) ($cardData['card_number'] ?? ''));
        $expiryMonth    = (int) ($cardData['expiry_month'] ?? 0);
        $expiryYear     = (int) ($cardData['expiry_year'] ?? 0);
        $cvv            = (string) ($cardData['cvv'] ?? '');
        $cardholderName = trim((string) ($cardData['cardholder_name'] ?? ''));

        // If caller passed a combined "MM/YY" expiry, parse it
        if (isset($cardData['expiry']) && ($expiryMonth === 0 || $expiryYear === 0)) {
            [$expiryMonth, $expiryYear] = $this->parseExpiry((string) $cardData['expiry']);
        }

        $this->validateCardNumber($cardNumber);
        $this->validateExpiry($expiryMonth, $expiryYear);
        $this->validateCvv($cvv);
        $this->validateCardholderName($cardholderName);

        $cardBrand       = $this->detectCardBrand($cardNumber);
        $last4           = substr($cardNumber, -4);
        $transactionId   = 'txn_' . Str::uuid()->toString();
        $paidAt          = now()->toIso8601String();

        return [
            'success'        => true,
            'gateway'        => 'card',
            'transaction_id' => $transactionId,
            'reference'      => $reference,
            'amount'         => round($amount, 2),
            'currency'       => strtoupper($currency),
            'card_last4'     => $last4,
            'card_brand'     => $cardBrand,
            'cardholder_name'=> $cardholderName,
            'paid_at'        => $paidAt,
        ];
    }

    // ── Validation helpers ────────────────────────────────────────────────

    private function validateAmount(float $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Payment amount must be greater than zero.');
        }
    }

    /**
     * Validate card number using the Luhn algorithm.
     */
    private function validateCardNumber(string $number): void
    {
        if ($number === '') {
            throw new \InvalidArgumentException('Card number is required.');
        }

        if (!ctype_digit($number)) {
            throw new \InvalidArgumentException('Card number must contain digits only.');
        }

        $length = strlen($number);
        if ($length < 13 || $length > 19) {
            throw new \InvalidArgumentException('Card number must be between 13 and 19 digits.');
        }

        if (!$this->luhnCheck($number)) {
            throw new \InvalidArgumentException('Card number is invalid.');
        }
    }

    /**
     * Luhn algorithm — standard card number checksum.
     */
    private function luhnCheck(string $number): bool
    {
        $sum    = 0;
        $flip   = false;
        $digits = array_reverse(str_split($number));

        foreach ($digits as $digit) {
            $n = (int) $digit;
            if ($flip) {
                $n *= 2;
                if ($n > 9) {
                    $n -= 9;
                }
            }
            $sum  += $n;
            $flip  = !$flip;
        }

        return ($sum % 10) === 0;
    }

    /**
     * Parse a "MM/YY" or "MM/YYYY" expiry string.
     */
    private function parseExpiry(string $expiry): array
    {
        $parts = explode('/', trim($expiry));
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException('Expiry must be in MM/YY or MM/YYYY format.');
        }

        $month = (int) trim($parts[0]);
        $year  = (int) trim($parts[1]);

        // Expand 2-digit year to 4-digit
        if ($year < 100) {
            $year += 2000;
        }

        return [$month, $year];
    }

    private function validateExpiry(int $month, int $year): void
    {
        if ($month < 1 || $month > 12) {
            throw new \InvalidArgumentException('Expiry month must be between 01 and 12.');
        }

        if ($year < 1000) {
            throw new \InvalidArgumentException('Expiry year is invalid.');
        }

        $now          = now();
        $currentYear  = (int) $now->format('Y');
        $currentMonth = (int) $now->format('n');

        if ($year < $currentYear || ($year === $currentYear && $month < $currentMonth)) {
            throw new \InvalidArgumentException('Card has expired.');
        }
    }

    private function validateCvv(string $cvv): void
    {
        if ($cvv === '') {
            throw new \InvalidArgumentException('CVV is required.');
        }

        if (!preg_match('/^\d{3,4}$/', $cvv)) {
            throw new \InvalidArgumentException('CVV must be 3 or 4 digits.');
        }
    }

    private function validateCardholderName(string $name): void
    {
        if ($name === '') {
            throw new \InvalidArgumentException('Cardholder name is required.');
        }

        if (strlen($name) > 255) {
            throw new \InvalidArgumentException('Cardholder name is too long.');
        }
    }

    /**
     * Detect card brand from the number prefix (IIN / BIN).
     */
    private function detectCardBrand(string $number): string
    {
        // Visa
        if (str_starts_with($number, '4')) {
            return 'VISA';
        }
        // Mastercard (51-55, 2221-2720)
        if (preg_match('/^5[1-5]/', $number) || preg_match('/^(222[1-9]|22[3-9]\d|2[3-6]\d{2}|27[01]\d|2720)/', $number)) {
            return 'MASTERCARD';
        }
        // American Express
        if (preg_match('/^3[47]/', $number)) {
            return 'AMEX';
        }
        // Discover
        if (preg_match('/^6(?:011|5\d{2})/', $number)) {
            return 'DISCOVER';
        }
        // JCB
        if (preg_match('/^35(?:2[89]|[3-8]\d)/', $number)) {
            return 'JCB';
        }
        // UnionPay
        if (str_starts_with($number, '62')) {
            return 'UNIONPAY';
        }

        return 'UNKNOWN';
    }
}
