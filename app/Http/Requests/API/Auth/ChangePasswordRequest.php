<?php

namespace App\Http\Requests\API\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ChangePasswordRequest extends FormRequest
{
    /**
     * The key to be used for the rate limiter.
     */
    protected function throttleKey(): string
    {
        return 'password-change:'.Auth::id();
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Handle a passed validation attempt.
     */
    protected function passedValidation(): void
    {
        // Apply rate limiting to prevent brute force attacks
        // Allow 5 password change attempts every 10 minutes
        RateLimiter::attempt(
            $this->throttleKey(),
            5,
            function () {
                $this->authenticate();
            },
            10 * 60
        );
    }

    /**
     * Attempt to authenticate the current password.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $user = Auth::user();

        if (! Hash::check($this->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('The current password is incorrect.'),
            ]);
        }

        // Prevent reusing the current password
        if (Hash::check($this->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => __('The new password must be different from your current password.'),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
                'confirmed',
                'different:current_password',
            ],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => __('Please enter your current password.'),
            'password.required' => __('Please enter a new password.'),
            'password.different' => __('The new password must be different from your current password.'),
            'password_confirmation.required' => __('Please confirm your new password.'),
            'password.confirmed' => __('The password confirmation does not match.'),
        ];
    }
}
