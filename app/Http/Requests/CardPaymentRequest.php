<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * CardPaymentRequest
 *
 * Validates all card-related fields submitted by the frontend.
 * Supports both split fields (expiry_month + expiry_year) and
 * combined "MM/YY" format for flexibility.
 */
class CardPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Payment method selector — the frontend sends one of these
            'payment_method'   => ['required', 'string', 'in:card,paypal,google_pay'],

            // Card details — required only when payment_method === 'card'
            'card_number'      => ['required_if:payment_method,card', 'nullable', 'string', 'min:13', 'max:19'],
            'cardholder_name'  => ['required_if:payment_method,card', 'nullable', 'string', 'max:255'],
            'cvv'              => ['required_if:payment_method,card', 'nullable', 'string', 'regex:/^\d{3,4}$/'],

            // Either combined "MM/YY" OR split month + year
            'expiry'           => ['nullable', 'string', 'regex:/^\d{2}\/\d{2,4}$/'],
            'expiry_month'     => ['nullable', 'integer', 'min:1', 'max:12'],
            'expiry_year'      => ['nullable', 'integer', 'min:2024'],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_method.required'     => 'Please select a payment method.',
            'payment_method.in'           => 'Payment method must be one of: card, paypal, google_pay.',
            'card_number.required_if'     => 'Card number is required for card payments.',
            'card_number.min'             => 'Card number is too short.',
            'card_number.max'             => 'Card number is too long.',
            'cardholder_name.required_if' => 'Cardholder name is required for card payments.',
            'cvv.required_if'             => 'CVV is required for card payments.',
            'cvv.regex'                   => 'CVV must be 3 or 4 digits.',
            'expiry.regex'                => 'Expiry must be in MM/YY or MM/YYYY format.',
            'expiry_month.min'            => 'Expiry month must be between 1 and 12.',
            'expiry_month.max'            => 'Expiry month must be between 1 and 12.',
        ];
    }

    /**
     * Extract card data ready for CardPaymentService::charge().
     */
    public function cardData(): array
    {
        $data = [
            'card_number'      => preg_replace('/\s+/', '', (string) $this->input('card_number', '')),
            'cvv'              => (string) $this->input('cvv', ''),
            'cardholder_name'  => trim((string) $this->input('cardholder_name', '')),
        ];

        // Parse expiry — support both "MM/YY" and split fields
        if ($this->filled('expiry')) {
            $data['expiry'] = (string) $this->input('expiry');
        } else {
            $data['expiry_month'] = (int) $this->input('expiry_month', 0);
            $data['expiry_year']  = (int) $this->input('expiry_year', 0);
        }

        return $data;
    }
}
