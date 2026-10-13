<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;

class ProfileService
{
    public function __construct(
        private ImageUploadService $imageUploadService
    ) {}

    public function updateProfile(User $user, array $data): User
    {
        $user->name = $data['name'] ?? $user->name;
        $user->email = $data['email'] ?? $user->email;
        $user->save();

        return $user;
    }

    public function updatePassword(User $user, string $oldPassword, string $newPassword): array
    {
        if (!Hash::check($oldPassword, $user->password)) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }

        $user->password = Hash::make($newPassword);
        $user->save();

        return ['success' => true, 'message' => 'Password updated successfully'];
    }

    public function updateAvatar(User $user, UploadedFile $avatarFile): string
    {
        $avatarPath = $this->imageUploadService->upload($avatarFile, 'user/avatar');

        $user->avatar = $avatarPath;
        $user->save();

        return $avatarPath;
    }
}
