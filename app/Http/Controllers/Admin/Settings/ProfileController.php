<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfilePictureRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        private ProfileService $profileService
    ) {}

    /**
     * Display the user's profile settings page.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();

        // Count active sessions for the current user
        $activeSessions = DB::table('sessions')
            ->where('user_id', (string) $user->id)
            ->count();

        return view('backend.layouts.settings.profile_settings', compact('user', 'activeSessions'));
    }

    /**
     * Update the user's profile.
     */
    public function UpdateProfile(UpdateProfileRequest $request): RedirectResponse
    {
        $user = Auth::user();

        $this->profileService->updateProfile($user, $request->validated());

        return redirect()->back()->with('t-success', 'Profile updated successfully');
    }

    /**
     * Update the user's password.
     */
    public function UpdatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $result = $this->profileService->updatePassword(
            $user,
            $request->input('old_password'),
            $request->input('password')
        );

        return redirect()->back()->with(
            $result['success'] ? 't-success' : 't-error',
            $result['message']
        );
    }

    /**
     * Update the user's profile picture.
     */
    public function UpdateProfilePicture(UpdateProfilePictureRequest $request): JsonResponse
    {
        $user = Auth::user();

        try {
            $avatarPath = $this->profileService->updateAvatar(
                $user,
                $request->file('profile_picture')
            );

            return response()->json([
                'success'   => true,
                'image_url' => \Illuminate\Support\Facades\Storage::disk('public')->url($avatarPath),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log out the current user from all devices by clearing all sessions.
     */
    public function logoutAllDevices(): RedirectResponse
    {
        $user = Auth::user();

        // Delete all sessions from the database
        $deleted = DB::table('sessions')->delete();

        // Log the current user out of THIS session too
        Auth::guard('web')->logout();

        // Invalidate the session and regenerate CSRF token
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        // Log the action
        \Illuminate\Support\Facades\Log::info("User #{$user->id} ({$user->email}) logged out all devices. {$deleted} session(s) cleared.");

        return redirect()->route('login')->with('t-success', "Logged out of all devices. {$deleted} session(s) cleared.");
    }
}
