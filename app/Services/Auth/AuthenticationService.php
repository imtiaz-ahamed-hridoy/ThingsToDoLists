<?php

namespace App\Services\Auth;

use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticationService
{
    use ApiResponse;

    /**
     * Handle user login
     */
    public function login(string $email, string $password): JsonResponse
    {
        if (! Auth::attempt(['email' => $email, 'password' => $password])) {
            return $this->error('The provided credentials do not match our records.', 401, [
                'email' => 'The provided credentials do not match our records.',
            ]);
        }

        $user = Auth::user();

        if ($user->role === 'Vendor') {
            Auth::logout();
            return $this->error('Access denied. Please use the Supplier Portal to log in.', 403);
        }

        if ($user->status === 'inactive') {
            Auth::logout();

            return $this->error('Your account has been deactivated by the administrator. Please contact the administrator for assistance.', 401);
        }

        return response()->json([
            'status' => true,
            'message' => 'Login Successful',
            'token_type' => 'Bearer',
            'token' => $user->createToken('AuthToken')->plainTextToken,
            'data' => $user,
        ]);
    }

    /**
     * Handle user logout
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return $this->ok('Logged out successfully.');
        } catch (\Exception $exception) {
            $message = $exception->getMessage() ?: 'Something went wrong.';
            return $this->error($message, 500);
        }
    }

    /**
     * Logout from all devices
     */
    public function logoutAll(Request $request): JsonResponse
    {
        try {
            $request->user()->tokens()->delete();

            return $this->ok('All devices have been successfully logged out.');
        } catch (\Exception $exception) {
            $message = $exception->getMessage() ?: 'Something went wrong.';
            return $this->error($message, 500);
        }
    }

    /**
     * Get user profile
     */
    public function getProfile(): JsonResponse
    {
        return $this->ok('Profile fetched successfully.', Auth::user());
    }
}
