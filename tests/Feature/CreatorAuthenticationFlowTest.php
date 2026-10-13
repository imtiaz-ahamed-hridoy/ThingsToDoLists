<?php

namespace Tests\Feature;

use App\Events\UserRegistered;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\CreatorCommunityApplication;
use Illuminate\Support\Facades\Hash;

class CreatorAuthenticationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_creator_registration_listener_is_wired()
    {
        $listeners = app('events')->getListeners(UserRegistered::class);

        $this->assertNotEmpty($listeners);
    }

    public function test_creator_registration_single_endpoint_success()
    {
        $response = $this->postJson('/api/v1/creator/register', [
            'email' => 'single-flow@example.com',
            'password' => 'StrongPass1!',
            'password_confirmation' => 'StrongPass1!',
            'partner_type' => 'creator',
            'full_name' => 'Jane Creator',
            'organization_name' => 'Jane Travel',
            'currency' => 'USD',
            'country' => 'France',
            'instagram_handle' => 'janetravel',
            'youtube_account' => 'Jane Travels',
            'main_channel' => 'instagram',
            'policy_agreed' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.email_verification_sent', true)
            ->assertJsonPath('data.verification_email', 'single-flow@example.com')
            ->assertJsonPath('data.creator.creator_type', 'content_creator')
            ->assertJsonMissingPath('data.token');

        $this->assertDatabaseHas('users', [
            'email' => 'single-flow@example.com',
            'role' => 'ContentCreator',
            'name' => 'Jane Creator',
        ]);

        $this->assertDatabaseHas('creators', [
            'creator_type' => 'content_creator',
            'instagram_handle' => 'janetravel',
            'main_channel' => 'instagram',
        ]);
    }

    public function test_creator_registration_accepts_live_frontend_payload_shape()
    {
        $response = $this->postJson('/api/v1/creator/register', [
            'email' => 'live-flow@example.com',
            'password' => 'StrongPass1!',
            'confirmPassword' => 'StrongPass1!',
            'type' => 'option1',
            'fullName' => 'Lina Creator',
            'orgName' => 'Lina Travel',
            'currency' => 'm@example.com',
            'country' => 'm@support.com',
            'instagramHandle' => '@linacreator',
            'youtubeHandle' => 'LinaCreatorTube',
            'tiktokHandle' => 'linacreator',
            'mainHandle' => 'm@google.com',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.creator.creator_type', 'content_creator')
            ->assertJsonPath('data.creator.currency', 'USD')
            ->assertJsonPath('data.creator.country', 'Saudi Arabia')
            ->assertJsonPath('data.creator.main_channel', 'instagram')
            ->assertJsonMissingPath('data.token');

        $this->assertDatabaseHas('users', [
            'email' => 'live-flow@example.com',
            'name' => 'Lina Creator',
        ]);

        $this->assertDatabaseHas('creators', [
            'creator_type' => 'content_creator',
            'currency' => 'USD',
            'country' => 'Saudi Arabia',
            'main_channel' => 'instagram',
        ]);
    }

    public function test_creator_login_success()
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => Hash::make('StrongPass1!'),
            'role' => 'ContentCreator'
        ]);

        $response = $this->postJson('/api/v1/creator/login', [
            'email' => 'login@example.com',
            'password' => 'StrongPass1!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_creator_portal_landing_requires_authentication()
    {
        $response = $this->getJson('/api/v1/creator-portal');

        $response->assertStatus(401);
    }

    public function test_creator_portal_landing_returns_authenticated_creator_data()
    {
        $user = User::factory()->create([
            'name' => 'Portal Creator',
            'email' => 'portal@example.com',
            'role' => 'ContentCreator',
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/v1/creator-portal');

        $response->assertStatus(200)
            ->assertJsonPath('data.user.email', 'portal@example.com')
            ->assertJsonPath('data.permissions.can_submit_community_application', true);
    }

    public function test_creator_global_application_get_returns_prefill_and_options()
    {
        $user = User::factory()->create([
            'name' => 'Global Creator',
            'email' => 'global-get@example.com',
            'role' => 'ContentCreator',
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/v1/creator-portal/global-application');

        $response->assertStatus(200)
            ->assertJsonPath('data.prefill.email', 'global-get@example.com')
            ->assertJsonStructure([
                'data' => [
                    'existing_application',
                    'prefill',
                    'options' => ['base_location', 'countries', 'discovered_via'],
                ],
            ]);
    }

    public function test_creator_global_application_submission_returns_submitted_application()
    {
        $user = User::factory()->create([
            'name' => 'Global Creator',
            'email' => 'global@example.com',
            'role' => 'ContentCreator',
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/v1/creator-portal/global-application', [
                'email' => 'global@example.com',
                'name' => 'Global Creator',
                'instagram_handle' => 'globalcreator',
                'instagram_followers' => 12000,
                'has_base_location' => true,
                'country' => 'Spain',
                'city' => 'Barcelona',
                'discovered_via' => 'ad_on_instagram',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.follow_up_eta_business_days', 7);
    }

    public function test_creator_global_application_accepts_live_frontend_payload_shape()
    {
        $user = User::factory()->create([
            'name' => 'Globe Live',
            'email' => 'global-live@example.com',
            'role' => 'ContentCreator',
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/v1/creator-portal/global-application', [
                'email' => 'global-live@example.com',
                'name' => 'Globe Live',
                'instagramHandle' => '@globelive',
                'instagramFollower' => '50000',
                'youtubeHandle' => '@globeliveyt',
                'tiktokHandle' => '@globelivetk',
                'websiteLink' => 'globelive.com',
                'type' => 'location-none',
                'currency' => 'm@google.com',
                'city' => 'Madrid',
                'source' => 'friend',
                'referred' => 'Creator Friend',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.country', 'Spain')
            ->assertJsonPath('data.has_base_location', false)
            ->assertJsonPath('data.discovered_via', 'referred_by_friend');
    }

    public function test_creator_sponsor_application_submission_returns_submitted_application()
    {
        $user = User::factory()->create([
            'name' => 'Sponsor Creator',
            'email' => 'sponsor@example.com',
            'role' => 'ContentCreator',
        ]);
        $token = $user->createToken('test')->plainTextToken;

        CreatorCommunityApplication::create([
            'user_id' => $user->id,
            'email' => 'sponsor@example.com',
            'name' => 'Sponsor Creator',
            'instagram_handle' => 'sponsorcreator',
            'instagram_followers' => 15000,
            'has_base_location' => true,
            'country' => 'France',
            'city' => 'Paris',
            'discovered_via' => 'ad_on_instagram',
            'status' => 'submitted',
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/v1/creator-portal/sponsor-application', [
                'is_community_member' => true,
                'name' => 'Sponsor Creator',
                'email' => 'sponsor@example.com',
                'instagram_url' => 'https://instagram.com/sponsorcreator',
                'preferred_currency' => 'USD',
                'reel_views_range' => '25k_to_50k',
                'planned_experience_date' => now()->addDays(10)->toDateString(),
                'no_go_experiences_accepted' => true,
                'feature_consent' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.follow_up_eta_business_days', 7);
    }

    public function test_creator_sponsor_application_accepts_live_frontend_payload_shape()
    {
        $user = User::factory()->create([
            'name' => 'Sponsor Live',
            'email' => 'sponsor-live@example.com',
            'role' => 'ContentCreator',
        ]);
        $token = $user->createToken('test')->plainTextToken;

        CreatorCommunityApplication::create([
            'user_id' => $user->id,
            'email' => 'sponsor-live@example.com',
            'name' => 'Sponsor Live',
            'instagram_handle' => '@sponsorlive',
            'instagram_followers' => 18000,
            'has_base_location' => true,
            'country' => 'France',
            'city' => 'Paris',
            'discovered_via' => 'ad_on_instagram',
            'status' => 'submitted',
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/v1/creator-portal/sponsor-application', [
                'community' => 'yes',
                'firstName' => 'Sponsor',
                'lastName' => 'Live',
                'email' => 'sponsor-live@example.com',
                'instagramHandle' => '@sponsorlive',
                'currency' => 'usd',
                'views' => 'less50',
                'date' => now()->addDays(12)->toDateString(),
                'experience' => true,
                'agree' => 'option1',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.preferred_currency', 'USD')
            ->assertJsonPath('data.reel_views_range', '25k_to_50k')
            ->assertJsonPath('data.feature_consent', true);
    }

    public function test_creator_login_fails_wrong_role()
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('StrongPass1!'),
            'role' => 'Admin'
        ]);

        $response = $this->postJson('/api/v1/creator/login', [
            'email' => 'admin@example.com',
            'password' => 'StrongPass1!',
        ]);

        $response->assertStatus(403);
    }

    public function test_creator_login_fails_if_email_not_verified()
    {
        $user = User::factory()->unverified()->create([
            'email' => 'unverified@example.com',
            'password' => Hash::make('StrongPass1!'),
            'role' => 'ContentCreator'
        ]);

        $response = $this->postJson('/api/v1/creator/login', [
            'email' => 'unverified@example.com',
            'password' => 'StrongPass1!',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('status', 403)
            ->assertJsonPath('message', 'Please verify your email address before logging in.');
    }

    public function test_creator_forgot_password_sends_otp_without_exposing_in_response()
    {
        $user = User::factory()->create([
            'email' => 'forgot@example.com',
            'role' => 'ContentCreator'
        ]);

        $response = $this->postJson('/api/v1/creator/forgot-password', [
            'email' => 'forgot@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonStructure(['data' => ['email']])
            ->assertJsonPath('data.email', 'forgot@example.com')
            ->assertJsonMissingPath('data.otp');
    }

    public function test_creator_forgot_password_fails_for_nonexistent_email()
    {
        $response = $this->postJson('/api/v1/creator/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(422);
    }

    public function test_creator_forgot_password_verify_otp_returns_reset_token()
    {
        User::factory()->create([
            'email' => 'verify-forgot@example.com',
            'password' => Hash::make('StrongPass1!'),
            'role' => 'ContentCreator'
        ]);

        // Request forgot password OTP
        $forgotResponse = $this->postJson('/api/v1/creator/forgot-password', [
            'email' => 'verify-forgot@example.com',
        ]);

        $forgotResponse->assertStatus(200)
            ->assertJsonMissingPath('data.otp');

        $otp = \Illuminate\Support\Facades\DB::table('otps')
            ->where('identifier', 'verify-forgot@example.com')
            ->latest('id')
            ->value('token');
        $this->assertNotNull($otp);

        // Verify the OTP
        $response = $this->postJson('/api/v1/creator/password/forgot/verify', [
            'email' => 'verify-forgot@example.com',
            'otp' => $otp,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonStructure(['data' => ['token', 'expired_in']]);
    }

    public function test_creator_forgot_password_verify_invalid_otp_returns_error()
    {
        User::factory()->create([
            'email' => 'bad-otp-forgot@example.com',
            'password' => Hash::make('StrongPass1!'),
            'role' => 'ContentCreator'
        ]);

        $this->postJson('/api/v1/creator/forgot-password', [
            'email' => 'bad-otp-forgot@example.com',
        ]);

        $response = $this->postJson('/api/v1/creator/password/forgot/verify', [
            'email' => 'bad-otp-forgot@example.com',
            'otp' => '000000',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('status', false);
    }

    public function test_creator_password_reset_success()
    {
        $user = User::factory()->create([
            'email' => 'reset-me@example.com',
            'password' => Hash::make('StrongPass1!'),
            'role' => 'ContentCreator'
        ]);

        // Request forgot password OTP
        $forgotResponse = $this->postJson('/api/v1/creator/forgot-password', [
            'email' => 'reset-me@example.com',
        ]);
        $forgotResponse->assertStatus(200);

        $otp = \Illuminate\Support\Facades\DB::table('otps')
            ->where('identifier', 'reset-me@example.com')
            ->latest('id')
            ->value('token');

        // Verify OTP to get reset token
        $verifyResponse = $this->postJson('/api/v1/creator/password/forgot/verify', [
            'email' => 'reset-me@example.com',
            'otp' => $otp,
        ]);
        $resetToken = $verifyResponse->json('data.token');

        // Reset password
        $response = $this->postJson('/api/v1/creator/password/reset', [
            'email' => 'reset-me@example.com',
            'token' => $resetToken,
            'password' => 'NewStrongPass1!',
            'password_confirmation' => 'NewStrongPass1!',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Password has been reset successfully');

        // Verify the new password works
        $this->assertTrue(Hash::check('NewStrongPass1!', $user->fresh()->password));
    }

    public function test_creator_email_verify_with_valid_otp_returns_token()
    {
        // Register a creator first
        $this->postJson('/api/v1/creator/register', [
            'email' => 'verify-flow@example.com',
            'password' => 'StrongPass1!',
            'password_confirmation' => 'StrongPass1!',
            'partner_type' => 'creator',
            'full_name' => 'Verify Creator',
            'currency' => 'USD',
            'country' => 'France',
            'instagram_handle' => 'verifycreator',
            'main_channel' => 'instagram',
        ]);

        $user = User::where('email', 'verify-flow@example.com')->first();
        $this->assertNotNull($user);

        // Fetch OTP generated during registration from DB
        $otp = \Illuminate\Support\Facades\DB::table('otps')
            ->where('identifier', 'verify-flow@example.com')
            ->latest('id')
            ->value('token');
        $this->assertNotNull($otp);

        // Verify email with the OTP
        $response = $this->postJson('/api/v1/creator/email/verify', [
            'email' => 'verify-flow@example.com',
            'otp' => $otp,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonStructure(['data' => ['token', 'token_type']]);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_creator_email_verify_with_invalid_otp_returns_error()
    {
        $this->postJson('/api/v1/creator/register', [
            'email' => 'bad-otp@example.com',
            'password' => 'StrongPass1!',
            'password_confirmation' => 'StrongPass1!',
            'partner_type' => 'creator',
            'full_name' => 'Bad OTP Creator',
            'currency' => 'USD',
            'country' => 'France',
            'instagram_handle' => 'badotp',
            'main_channel' => 'instagram',
        ]);

        $response = $this->postJson('/api/v1/creator/email/verify', [
            'email' => 'bad-otp@example.com',
            'otp' => '000000',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('status', false);
    }

    public function test_creator_email_resend_otp_sends_without_exposing_otp()
    {
        $this->postJson('/api/v1/creator/register', [
            'email' => 'resend-flow@example.com',
            'password' => 'StrongPass1!',
            'password_confirmation' => 'StrongPass1!',
            'partner_type' => 'creator',
            'full_name' => 'Resend Creator',
            'currency' => 'USD',
            'country' => 'France',
            'instagram_handle' => 'resendcreator',
            'main_channel' => 'instagram',
        ]);

        $response = $this->postJson('/api/v1/creator/email/verify/resend', [
            'email' => 'resend-flow@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonMissingPath('data.otp');
    }

    public function test_creator_email_resend_otp_fails_for_nonexistent_email()
    {
        $response = $this->postJson('/api/v1/creator/email/verify/resend', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(422);
    }

    public function test_creator_register_options_route_is_removed()
    {
        $response = $this->getJson('/api/v1/creator/register');

        $response->assertStatus(405);
    }

    public function test_legacy_creator_registration_step_route_is_removed()
    {
        $response = $this->postJson('/api/v1/creator/register/step1', [
            'email' => 'legacy@example.com',
        ]);

        $response->assertStatus(404);
    }

    public function test_legacy_creator_password_email_route_is_removed()
    {
        $response = $this->postJson('/api/v1/creator/password/email', [
            'email' => 'legacy@example.com',
        ]);

        $response->assertStatus(404);
    }
}
