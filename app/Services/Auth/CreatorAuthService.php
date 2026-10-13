<?php

namespace App\Services\Auth;

use App\Services\Auth\EmailVerificationService;
use App\Events\UserRegistered;
use App\Models\User;
use App\Models\Creator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreatorAuthService
{
    protected EmailVerificationService $emailVerificationService;

    public function __construct(EmailVerificationService $emailVerificationService)
    {
        $this->emailVerificationService = $emailVerificationService;
    }

    /**
     * Register a new content creator
     *
     * @throws Throwable
     */
    public function registerCreator(array $data): array
    {
        DB::beginTransaction();

        try {
            // Use full name as name
            $fullName = trim($data['full_name']);

            // Create user
            $user = User::create([
                'name' => $fullName,
                'email' => $data['email'],
                'password' => bcrypt($data['password']),
                'role' => 'ContentCreator',
                'is_agree' => true,
            ]);

            // Create Creator profile
            $creator = Creator::create([
                'user_id' => $user->id,
                'creator_type' => $data['creator_type'],
                'organization_name' => $data['organization_name'] ?? null,
                'currency' => $data['currency'],
                'country' => $data['country'],
                'instagram_handle' => $data['instagram_handle'] ?? null,
                'youtube_account' => $data['youtube_account'] ?? null,
                'tiktok_account' => $data['tiktok_account'] ?? null,
                'main_channel' => $data['main_channel'],
            ]);

            // Send verification OTP
            try {
                $this->emailVerificationService->sendVerificationOtp($user->email);
            } catch (\Exception $e) {
                // Log the email failure but don't stop the registration process
                Log::error('Creator registration email failed: ' . $e->getMessage());
            }

            // Commit transaction
            DB::commit();

            return [
                'user' => $user->load('creator'),
            ];
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
