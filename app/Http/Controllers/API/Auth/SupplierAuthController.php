<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\Auth\LoginRequest;
use App\Services\Auth\SupplierAuthService;
use App\Traits\ApiResponse;
use App\Traits\HasApiException;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Throwable;

class SupplierAuthController extends Controller
{
    use ApiResponse, HasApiException;

    protected SupplierAuthService $supplierAuthService;

    public function __construct(SupplierAuthService $supplierAuthService)
    {
        $this->supplierAuthService = $supplierAuthService;
    }

    /**
     * Handle supplier registration
     */
    public function register(\Illuminate\Http\Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Business Details
            'business_type' => ['required', 'string', 'in:individual,company'],
            'activities_count' => ['required', 'string'],
            'locations' => ['required', 'array'],
            'country' => ['required', 'string'],
            'currency' => ['required', 'string', 'max:10'],
            'brand_name' => ['required', 'string', 'max:255'],
            'website_link' => ['nullable', 'url', 'max:255'],

            // User Details
            'name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'string', 'email', 'max:80', 'unique:users,email'],

            // Password exact logic: Between 8 and 30 characters, numbers, 
            // one special char, uppercase, lowercase, no blank spaces
            'password' => [
                'required',
                'string',
                'min:8',
                'max:30',
                'regex:/[a-z]/',      // lowercase
                'regex:/[A-Z]/',      // uppercase
                'regex:/[0-9]/',      // number
                'regex:/[@$!%*#?&^]/', // special character
                'regex:/^\S*$/u',     // no spaces
                function ($attribute, $value, $fail) use ($request) {
                    $email = $request->input('email');
                    if ($email) {
                        $emailParts = explode('@', $email);
                        $emailLocalPart = $emailParts[0] ?? '';

                        if (!empty($emailLocalPart) && stripos($value, $emailLocalPart) !== false) {
                            $fail('The password cannot contain part of your email address.');
                        }
                    }
                },
            ],

            'is_agree' => ['required', 'accepted'],
        ], [
            'password.regex' => 'The password must contain at least one lowercase letter, one uppercase letter, one digit, one special character, and no spaces.',
            'is_agree.accepted' => 'You must agree to the Terms and Conditions and Privacy Policy.',
            'currency.max' => 'The currency must not exceed 10 characters.',
        ]);

        try {
            $result = $this->supplierAuthService->registerSupplier($validated);

            return response()->json([
                'status' => true,
                'message' => 'Supplier registration completed successfully.',
                'token_type' => 'Bearer',
                'token' => $result['token'],
                'data' => $result['user'],
            ], 201);
        } catch (Exception | Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Handle supplier login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            if (! Auth::attempt($request->only('email', 'password'))) {
                return $this->error('The provided credentials do not match our records.', 401, [
                    'email' => 'The provided credentials do not match our records.',
                ]);
            }

            $user = Auth::user();

            // Ensure the user has the Vendor role
            if ($user->role !== 'Vendor') {
                Auth::logout();
                return $this->error('Access denied. This login portal is restricted to Supply Partners only.', 403);
            }

            if ($user->status === 'inactive') {
                Auth::logout();
                return $this->error('Your account has been deactivated by the administrator.', 401);
            }

            return response()->json([
                'status' => true,
                'message' => 'Login Successful',
                'token_type' => 'Bearer',
                'token' => $user->createToken('AuthToken')->plainTextToken,
                'data' => $user->load('vendor'),
            ]);
        } catch (Exception | Throwable $e) {
            return $this->handleException($e);
        }
    }
}
