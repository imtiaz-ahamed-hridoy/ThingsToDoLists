<?php

namespace App\Http\Controllers\API\Creator;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\Auth\EmailVerifyRequest;
use App\Http\Requests\API\Auth\ForgotPasswordRequest;
use App\Http\Requests\API\Auth\ResendOTPRequest;
use App\Http\Requests\API\Auth\ResetPasswordRequest;
use App\Http\Requests\API\Auth\VerifyOTPRequest;
use App\Models\User;
use App\Services\Auth\CreatorAuthService;
use App\Services\Auth\EmailVerificationService;
use App\Services\Auth\PasswordService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreatorAuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CreatorAuthService $creatorAuthService,
        protected EmailVerificationService $emailVerificationService,
        protected PasswordService $passwordService
    ) {}

    public function register(Request $request): JsonResponse
    {
        $request->merge($this->prepareRegisterPayload($request));

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[@$!%*?&]/',
            ],
            'partner_type' => ['required', 'string', 'in:creator,agent,reseller'],
            'full_name' => ['required', 'string', 'max:255'],
            'organization_name' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'string', 'in:USD,EUR,GBP,AUD,CHF,CAD'],
            'country' => ['required', 'string', 'max:255'],
            'instagram_handle' => ['nullable', 'string', 'max:255'],
            'youtube_account' => ['nullable', 'string', 'max:255'],
            'tiktok_account' => ['nullable', 'string', 'max:255'],
            'main_channel' => ['nullable', 'string', 'in:instagram,youtube,tiktok'],
            'policy_agreed' => ['nullable', 'boolean'],
            'is_agree' => ['nullable', 'boolean'],
        ], [
            'password.regex' => 'Your password must contain at least 1 lowercase letter, 1 uppercase letter, 1 special character, and 1 numeral.',
        ]);

        $this->ensureAtLeastOneSocialMedia(
            $validated['instagram_handle'] ?? null,
            $validated['youtube_account'] ?? null,
            $validated['tiktok_account'] ?? null
        );

        $result = $this->creatorAuthService->registerCreator([
            'email' => $validated['email'],
            'password' => $validated['password'],
            'creator_type' => match ($validated['partner_type']) {
                'creator' => 'content_creator',
                'agent' => 'travel_agent',
                'reseller' => 'in_destination_guide_reseller',
            },
            'full_name' => $validated['full_name'],
            'organization_name' => $validated['organization_name'] ?? null,
            'currency' => $validated['currency'],
            'country' => $validated['country'],
            'instagram_handle' => $validated['instagram_handle'] ?? null,
            'youtube_account' => $validated['youtube_account'] ?? null,
            'tiktok_account' => $validated['tiktok_account'] ?? null,
            'main_channel' => $validated['main_channel'],
            'is_agree' => true,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Content creator registration completed successfully. Please verify your email address.',
            'data' => [
                'email_verification_sent' => true,
                'verification_email' => $result['user']->email,
                'creator' => $result['user']->creator,
                'user' => $result['user'],
            ],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->role !== 'ContentCreator') {
            return $this->error('Unauthorized access. This portal is for Content Creators only.', 403);
        }

        if ($user->status === 'inactive') {
            return $this->error('Your account has been deactivated by the administrator.', 401);
        }

        if ($user->email_verified_at === null) {
            return $this->error('Please verify your email address before logging in.', 403);
        }

        $token = $user->createToken('creator_token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Login successful.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'avatar' => $user->avatar,
                ],
            ],
        ]);
    }

    /**
     * Verify creator email with OTP and return a Bearer token.
     */
    public function verifyEmail(EmailVerifyRequest $request): JsonResponse
    {
        $verification = $this->emailVerificationService->verifyEmail(
            $request->email,
            $request->otp
        );

        if ($verification['status']) {
            return response()->json([
                'status' => true,
                'message' => 'Your email has been successfully verified.',
                'data' => [
                    'token_type' => 'Bearer',
                    'token' => $verification['token'] ?? null,
                ],
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => $verification['message'],
        ], $verification['code']);
    }

    /**
     * Resend email verification OTP to creator.
     */
    public function resendEmailVerifyOTP(ResendOTPRequest $request): JsonResponse
    {
        $result = $this->emailVerificationService->sendVerificationOtp($request->email);

        if ($result['status']) {
            return response()->json([
                'status' => true,
                'message' => 'A new verification code has been sent to your email.',
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => $result['message'],
        ], $result['code']);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $result = $this->passwordService->processForgotPassword($request->email);

        return response()->json([
            'status' => true,
            'message' => 'Password reset OTP has been sent to your email.',
            'data' => [
                'email' => $request->email,
            ],
        ]);
    }

    /**
     * Verify OTP for password reset and return a reset token.
     */
    public function verifyForgotPasswordOTP(VerifyOTPRequest $request): JsonResponse
    {
        $result = $this->passwordService->verifyPasswordResetOtp(
            $request->email,
            $request->otp
        );

        if ($result['status']) {
            return response()->json([
                'status' => true,
                'message' => $result['message'],
                'data' => [
                    'token' => $result['token'],
                    'expired_in' => '15 minutes',
                ],
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => $result['message'],
        ], $result['code']);
    }

    /**
     * Reset password using a valid reset token.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $result = $this->passwordService->resetPassword(
            $request->email,
            $request->password,
            $request->token
        );

        if ($result['status']) {
            return response()->json([
                'status' => true,
                'message' => $result['message'],
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => $result['message'],
        ], $result['code']);
    }

    private function ensureAtLeastOneSocialMedia(?string $instagramHandle, ?string $youtubeAccount, ?string $tiktokAccount): void
    {
        $hasSocial = filled($instagramHandle) || filled($youtubeAccount) || filled($tiktokAccount);

        if (! $hasSocial) {
            throw ValidationException::withMessages([
                'instagram_handle' => ['Please provide at least one social media link (Instagram, YouTube, or TikTok).'],
                'youtube_account' => ['Please provide at least one social media link (Instagram, YouTube, or TikTok).'],
                'tiktok_account' => ['Please provide at least one social media link (Instagram, YouTube, or TikTok).'],
            ]);
        }
    }

    private function prepareRegisterPayload(Request $request): array
    {
        $instagramHandle = $request->input('instagram_handle', $request->input('instagramHandle'));
        $youtubeAccount = $request->input('youtube_account', $request->input('youtubeHandle'));
        $tiktokAccount = $request->input('tiktok_account', $request->input('tiktokHandle'));

        return [
            'email' => $request->input('email'),
            'password' => $request->input('password'),
            'password_confirmation' => $request->input('password_confirmation', $request->input('confirmPassword')),
            'partner_type' => $this->normalizePartnerTypeInput($request->input('partner_type', $request->input('type'))),
            'full_name' => $request->input('full_name', $request->input('fullName')),
            'organization_name' => $request->input('organization_name', $request->input('orgName')),
            'currency' => $this->normalizeCurrencyValue($request->input('currency')),
            'country' => $this->normalizeCountryValue($request->input('country')),
            'instagram_handle' => $instagramHandle,
            'youtube_account' => $youtubeAccount,
            'tiktok_account' => $tiktokAccount,
            'main_channel' => $this->normalizeMainChannelValue($request->input('main_channel', $request->input('mainHandle'))),
            'policy_agreed' => $this->resolveBooleanInput($request, ['policy_agreed', 'policyAgreed']),
            'is_agree' => $this->resolveBooleanInput($request, ['is_agree', 'isAgree']),
        ];
    }

    private function resolveBooleanInput(Request $request, array $keys): bool
    {
        foreach ($keys as $key) {
            if ($request->has($key)) {
                return $request->boolean($key);
            }
        }

        return false;
    }

    private function normalizePartnerTypeInput(?string $value): ?string
    {
        return match ($value) {
            'option1', 'creator', 'content_creator' => 'creator',
            'option2', 'agent', 'travel_agent' => 'agent',
            'option3', 'reseller', 'in_destination_guide_reseller' => 'reseller',
            default => $value,
        };
    }

    private function normalizeCurrencyValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match (Str::lower(trim($value))) {
            'usd', 'us dollar $', 'us dollars', 'm@example.com' => 'USD',
            'eur', 'euro', 'euro €', 'm@google.com' => 'EUR',
            'gbp', 'pound sterling £', 'm@support.com' => 'GBP',
            'aud', 'australian', 'australian dollar a$' => 'AUD',
            'chf', 'swiss', 'swiss franc chf' => 'CHF',
            'cad', 'canadian', 'canadian dollar ca$' => 'CAD',
            default => Str::upper(trim($value)),
        };
    }

    private function normalizeCountryValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match (Str::lower(trim($value))) {
            'm@example.com' => 'France',
            'm@google.com' => 'Spain',
            'm@support.com' => 'Saudi Arabia',
            default => trim($value),
        };
    }

    private function normalizeMainChannelValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match (Str::lower(trim($value))) {
            'm@example.com' => 'instagram',
            'm@google.com', 'instagram' => 'instagram',
            'm@support.com', 'you tube', 'youtube' => 'youtube',
            'dffm@support.com', 'tik tok', 'tiktok' => 'tiktok',
            default => Str::lower(trim($value)),
        };
    }


}
