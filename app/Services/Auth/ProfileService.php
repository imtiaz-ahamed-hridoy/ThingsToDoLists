<?php

namespace App\Services\Auth;

use App\Http\Requests\API\Auth\UpdateProfileRequest;
use App\Models\User;
use App\Traits\ApiResponse;
use Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Storage;

class ProfileService
{
    use ApiResponse;

    /**
     * Update user profile
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = Auth::user();
        $validatedData = $request->validated();

        // Handle avatar upload
        if ($request->hasFile('avatar') && $request->file('avatar')->isValid()) {
            $validatedData['avatar'] = $this->updateAvatar($user, $request->file('avatar'));
        }

        // Update user data
        $user->update($validatedData);

        return $this->ok('Profile updated successfully.', $user);
    }

    /**
     * Update user avatar
     */
    protected function updateAvatar(User $user, UploadedFile $avatar): string
    {
        // Delete old avatar if exists
        $rawAvatar = $user->getRawOriginal('avatar');
        if ($rawAvatar && Storage::disk('public')->exists($rawAvatar)) {
            Storage::disk('public')->delete($rawAvatar);
        }

        // Store new avatar
        return $avatar->store('user/avatar', 'public');
    }
}
