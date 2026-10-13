<?php

namespace App\Services\Auth;

use App\Repositories\Contracts\UserRepositoryInterface;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class RegistrationService
{
    use ApiResponse;

    protected UserRepositoryInterface $userRepository;

    protected EmailVerificationService $emailVerificationService;

    public function __construct(
        UserRepositoryInterface $userRepository,
        EmailVerificationService $emailVerificationService
    ) {
        $this->userRepository = $userRepository;
        $this->emailVerificationService = $emailVerificationService;
    }

    /**
     * Register a new user
     *
     * @throws Throwable
     */
    public function registerUser(array $userData): array
    {
        DB::beginTransaction();

        try {
            // Create user
            $user = $this->userRepository->create($userData);

            // Send verification OTP directly
            try {
                $this->emailVerificationService->sendVerificationOtp($user->email);
            } catch (Throwable $mailException) {
                // Log the mail error but don't stop registration
                \Illuminate\Support\Facades\Log::error('Registration Mail Error: ' . $mailException->getMessage());
            }

            // Commit transaction
            DB::commit();

            return [
                'user' => $user,
            ];
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
