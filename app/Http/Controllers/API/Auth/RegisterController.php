<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\Auth\EmailVerifyRequest;
use App\Http\Requests\API\Auth\ForgotPasswordRequest;
use App\Http\Requests\API\Auth\RegistrationRequest;
use App\Http\Requests\API\Auth\ResendOTPRequest;
use App\Http\Requests\API\Auth\ResetPasswordRequest;
use App\Http\Requests\API\Auth\VerifyOTPRequest;
use App\Models\User;
use App\Services\Auth\AuthFacade;
use App\Services\Product\WishlistService;
use App\Traits\ApiResponse;
use App\Traits\HasApiException;
use Exception;
use Illuminate\Http\JsonResponse;
use Throwable;

class RegisterController extends Controller
{
    use ApiResponse, HasApiException;

    protected AuthFacade $authFacade;
    protected WishlistService $wishlistService;

    /**
     * RegisterController constructor.
     */
    public function __construct(
        AuthFacade $authFacade,
        WishlistService $wishlistService
    ) {
        $this->authFacade = $authFacade;
        $this->wishlistService = $wishlistService;
    }

    /**
     * Handle user registration
     */
    public function register(RegistrationRequest $request): JsonResponse
    {
        try {
            $result = $this->authFacade->registerUser($request->validated());

            return response()->json([
                'status' => true,
                'message' => 'Registration completed successfully.',
                'data' => $result['user'],
            ], 201);
        } catch (Exception | Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Resend OTP to user
     */
    public function resendEmailVerifyOTP(ResendOTPRequest $request): JsonResponse
    {
        try {
            // Find user by email and send OTP to email
            $result = $this->authFacade->sendVerificationOtp($request->email);
            if (! $result['status']) {
                return $this->error($result['message'], $result['code']);
            }

            // Send a successful response
            return $this->ok('A new verification code has been sent to your email.');
        } catch (Exception | Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Verify user email with OTP.
     * On successful verification, syncs any guest wishlist to the now-registered user.
     */
    public function verifyEmail(EmailVerifyRequest $request): JsonResponse
    {
        try {
            // Verify OTP and update email verification status
            $verification = $this->authFacade->verifyEmail($request->email, $request->otp);

            if ($verification['status']) {
                // Sync guest wishlist to the newly verified user account
                $guestId = $request->header('X-Guest-Wishlist');
                if ($guestId) {
                    $user = User::where('email', $request->email)->first();
                    if ($user) {
                        $this->wishlistService->syncGuestToUser($user->id, $guestId);
                    }
                }

                return $this->ok('Your email has been successfully verified.', [
                    'token_type' => 'Bearer',
                    'token' => $verification['token'] ?? null,
                ]);
            }

            return $this->error($verification['message'], $verification['code']);
        } catch (Exception | Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Handle forgot password request by sending an OTP
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            // send forget password otp
            $result = $this->authFacade->processForgotPassword($request->email);

            // return successful response
            return $this->ok('Password reset OTP has been sent to your email.');
        } catch (Exception | Throwable $e) {
            // handle exception
            return $this->handleException($e);
        }
    }

    /**
     * Verify an OTP for password reset.
     *
     * @param  VerifyOTPRequest  $request  The validated request containing email and OTP
     * @return JsonResponse The response with success/error message and token
     */
    public function verifyForgotPasswordOTP(VerifyOTPRequest $request): JsonResponse
    {
        try {
            $result = $this->authFacade->verifyPasswordResetOtp(
                $request->email,
                $request->otp
            );

            if ($result['status']) {
                return $this->ok(
                    $result['message'],
                    [
                        'token' => $result['token'],
                        'expired_in' => '15 minutes',
                    ]
                );
            }

            return $this->error($result['message'], $result['code']);
        } catch (Exception | Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Reset a user's password using a valid token
     *
     * @param  ResetPasswordRequest  $request  Contains validated email, token, and new password
     * @return JsonResponse Returns success or error response
     *
     * @throws Exception When an unexpected error occurs during password reset
     */
    public function resetPassword(ResetPasswordRequest $request)
    {
        try {
            $result = $this->authFacade->resetPassword(
                $request->email,
                $request->password,
                $request->token
            );

            if ($result['status']) {
                return $this->ok('Your password has been successfully reset.');
            }

            return $this->error(
                $result['message'] ?? 'Failed to reset password',
                $result['code'] ?? 400
            );
        } catch (Exception | Throwable $e) {
            return $this->handleException($e);
        }
    }
}
