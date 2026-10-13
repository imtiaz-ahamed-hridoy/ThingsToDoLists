<?php

namespace App\Services\Auth;

use App\Http\Requests\API\Auth\ChangePasswordRequest;
use App\Http\Requests\API\Auth\UpdateProfileRequest;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * This facade class provides a simplified interface to all auth services
 * It helps maintain backward compatibility while using the new service structure
 */
class AuthFacade
{
    protected RegistrationService $registrationService;

    protected EmailVerificationService $emailVerificationService;

    protected PasswordService $passwordService;

    protected AuthenticationService $authenticationService;

    protected ProfileService $profileService;

    public function __construct(
        RegistrationService $registrationService,
        EmailVerificationService $emailVerificationService,
        PasswordService $passwordService,
        AuthenticationService $authenticationService,
        ProfileService $profileService
    ) {
        $this->registrationService = $registrationService;
        $this->emailVerificationService = $emailVerificationService;
        $this->passwordService = $passwordService;
        $this->authenticationService = $authenticationService;
        $this->profileService = $profileService;
    }

    /**
     * Register a new user
     *
     * @throws Throwable
     */
    public function registerUser(array $userData): array
    {
        return $this->registrationService->registerUser($userData);
    }

    /**
     * Send verification OTP to user email
     *
     * @throws Exception
     */
    public function sendVerificationOtp(string $email): array
    {
        return $this->emailVerificationService->sendVerificationOtp($email);
    }

    /**
     * Verify user email with OTP
     */
    public function verifyEmail(string $email, string $otp): array
    {
        return $this->emailVerificationService->verifyEmail($email, $otp);
    }

    /**
     * Process forgot password request
     *
     * @throws Exception
     */
    public function processForgotPassword(string $email): array
    {
        return $this->passwordService->processForgotPassword($email);
    }

    /**
     * Verify password reset OTP
     */
    public function verifyPasswordResetOtp(string $email, string $otp): array
    {
        return $this->passwordService->verifyPasswordResetOtp($email, $otp);
    }

    /**
     * Reset password with token
     */
    public function resetPassword(string $email, string $password, string $token): array
    {
        return $this->passwordService->resetPassword($email, $password, $token);
    }

    /**
     * Handle user login
     */
    public function login(string $email, string $password): JsonResponse
    {
        return $this->authenticationService->login($email, $password);
    }

    /**
     * Handle user logout
     */
    public function logout(Request $request): JsonResponse
    {
        return $this->authenticationService->logout($request);
    }

    /**
     * Logout from all devices
     */
    public function logoutAll(Request $request): JsonResponse
    {
        return $this->authenticationService->logoutAll($request);
    }

    /**
     * Update user profile
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        return $this->profileService->updateProfile($request);
    }

    /**
     * Change user password
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        return $this->passwordService->changePassword($request);
    }

    /**
     * Get user profile
     */
    public function getProfile(): JsonResponse
    {
        return $this->authenticationService->getProfile();
    }
}
