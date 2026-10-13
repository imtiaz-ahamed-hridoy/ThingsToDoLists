<?php

namespace App\Services\Auth;

use App\Events\EmailVerified;
use App\Models\User;
use App\Notifications\VerifyEmailOtp;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Traits\ApiResponse;
use Exception;
use Ichtrojan\Otp\Otp;

class EmailVerificationService
{
    use ApiResponse;

    protected Otp $otp;

    protected UserRepositoryInterface $userRepository;

    /**
     * EmailVerificationService constructor.
     */
    public function __construct(
        Otp $otp,
        UserRepositoryInterface $userRepository
    ) {
        $this->otp = $otp;
        $this->userRepository = $userRepository;
    }

    /**
     * Generate OTP and send verification email
     *
     * @throws Exception For other unexpected errors
     */
    public function sendVerificationOtp(string $email): array
    {
        // Find a user by email
        $user = $this->userRepository->findByEmail($email);

        // Check user if empty throw exception
        if (! $user) {
            return [
                'status' => false,
                'message' => 'User not found',
                'code' => 404,
            ];
        }

        // Check if user already verified throw validation exception
        if ($user->hasVerifiedEmail()) {
            return [
                'status' => false,
                'message' => 'User already verified',
                'code' => 403,
            ];
        }

        $this->sendOtp($user, VerifyEmailOtp::class);

        return [
            'status' => true,
            'message' => 'OTP sent to your email',
            'code' => 200,
        ];
    }

    /**
     * Generate OTP and send notification
     *
     * @param  string  $notificationType  The notification class to use
     * @param  int  $digits  Number of OTP digits
     * @param  int  $expiry  Expiry time in seconds
     *
     * @throws Exception
     */
    protected function sendOtp(User $user, string $notificationType, int $digits = 6, int $expiry = 10): string
    {
        // Generate OTP first
        $otp = $this->otp->generate($user->email, 'numeric', $digits, $expiry);

        try {
            $user->notify(new $notificationType($otp->token));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('OTP email sending failed: ' . $e->getMessage());
        }

        // Always return the OTP token regardless of email success/failure
        return $otp->token;
    }

    /**
     * Verify user email with OTP
     */
    public function verifyEmail(string $email, string $otp): array
    {

        // find user by email
        $user = $this->userRepository->findByEmail($email);
        if (! $user) {
            return [
                'status' => false,
                'message' => 'User not found',
                'code' => 404,
            ];
        }
        // Return true if the user is already verified
        if ($user->hasVerifiedEmail()) {
            return [
                'status' => false,
                'message' => 'Email already verified',
                'code' => 403,
            ];
        }

        // Find valid OTP and validate it
        $verify = (new Otp)->validate($user->email, $otp);

        // If OTP is invalid, throw validation exception
        if (! $verify->status) {
            return [
                'status' => false,
                'message' => $verify->message,
                'code' => 400,
            ];
        }

        // Mark email as verified
        $verified = $this->userRepository->markEmailAsVerified($user);

        if (! $verified) {
            return [
                'status' => false,
                'message' => 'Failed to verify email',
                'code' => 500,
            ];
        }

        // Dispatch event if verified successfully
        event(new EmailVerified($user));

        $token = $user->createToken('AuthToken')->plainTextToken;

        return [
            'status' => true,
            'message' => 'Email verified successfully',
            'code' => 200,
            'token' => $token,
        ];
    }
}
