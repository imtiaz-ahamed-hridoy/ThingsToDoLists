<?php

namespace App\Services\Auth;

use App\Services\Auth\EmailVerificationService;
use App\Events\UserRegistered;
use App\Models\User;
use App\Models\Vendor;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\DB;
use Throwable;
class SupplierAuthService
{
    use ApiResponse;

    protected EmailVerificationService $emailVerificationService;

    public function __construct(EmailVerificationService $emailVerificationService)
    {
        $this->emailVerificationService = $emailVerificationService;
    }

    /**
     * Register a new supplier (vendor)
     *
     * @throws Throwable
     */
    public function registerSupplier(array $data): array
    {
        DB::beginTransaction();

        try {
            // Create user for the supplier
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => bcrypt($data['password']),
                'role' => 'Vendor',
                'is_agree' => true,
            ]);

            // Create Vendor profile linked to the user
            $vendor = Vendor::create([
                'user_id' => $user->id,
                'name' => $data['brand_name'],
                'link' => $data['website_link'] ?? null,
                'country' => $data['country'],
                'currency' => $data['currency'],
                'business_type' => $data['business_type'],
                'activities_count' => $data['activities_count'],
                'locations' => $data['locations'],
            ]);

            // Send verification OTP
            try {
                $this->emailVerificationService->sendVerificationOtp($user->email);
            } catch (\Exception $e) {
                // Log the email failure but don't stop the registration process
                \Illuminate\Support\Facades\Log::error('Vendor registration email failed: ' . $e->getMessage());
            }

            // Generate token
            $token = $user->createToken('AuthToken')->plainTextToken;

            // Commit transaction
            DB::commit();

            return [
                'user' => $user->load('vendor'),
                'token' => $token,
            ];
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::error("SupplierAuthService error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            DB::rollBack();
            throw $e;
        }
    }
}
