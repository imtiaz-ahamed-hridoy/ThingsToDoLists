<?php

namespace App\Http\Controllers\API\Auth;

use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ProfileUpdateController extends Controller
{
    use ApiResponse;

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'old_password' => 'required|string',
            'password' => 'required|string|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {

           $user = Auth::user();

            if (!Hash::check($request->old_password, $user->password)) {
                return $this->error('Incorrect Current Password', 402);
            }

            $user->password = Hash::make($request->password);
            $user->save();
            return $this->ok('Password changed successfully');
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage(), 404);
        }
    }

    public function profileUrl(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'url' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {

           $user = Auth::user();

            $url = $request->url;

            if ($user->url == $url) {
                return $this->error('Profile url cannot be same as your current profile url');
            }

            $exist_url = User::where('url', $url)->exists();

            if ($exist_url) {
                return $this->error('Profile url already exists');
            }

            $user->url = $url ?? $user->url;
            $user->save();

            return $this->ok('Profile info updated successfully');
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage(), 404);
        }
    }

    public function profileAvatarUpload(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        try {
            if ($request->hasFile('avatar')) {
                // Delete old photo if exists
                $rawAvatar = $user->getRawOriginal('avatar');
                if ($rawAvatar) {
                    $this->deleteStoredFile($rawAvatar);
                }

                // Upload new photo
                $imagePath = $request->file('avatar')->storeAs(
                    'uploads/avatar',
                    time() . '_' . $request->file('avatar')->getClientOriginalName(),
                    'public'
                );

                $user->avatar = $imagePath;
                $user->save();
            }

            return $this->success('Profile photo updated successfully', $user, 200);

        } catch (\Exception $exception) {
            return response()->json([
                'status'  => false,
                'message' => $exception->getMessage(),
            ], 500);
        }
    }

   public function profileAvatarRemove()
    {
        try {

            $user = Auth::user();

            $rawAvatar = $user->getRawOriginal('avatar');
            if ($rawAvatar) {
                // Delete the file from storage
                $this->deleteStoredFile($rawAvatar);

                // Remove photo path from DB
                $user->avatar = null;
                $user->save();

                return $this->success('Profile photo removed successfully', $user, 200);
            }

            return $this->error('Profile photo not found', 404);

        } catch (\Exception $exception) {
            return response()->json([
                'status'  => false,
                'message' => $exception->getMessage()
            ], 500);
        }
    }

    private function deleteStoredFile(?string $path): void
    {
        if (!$path) {
            return;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            $parsedUrl = parse_url($path);
            $path = $parsedUrl['path'] ?? $path;
        }

        $cleanPath = ltrim($path, '/');
        if (strpos($cleanPath, 'storage/') === 0) {
            $cleanPath = substr($cleanPath, 8);
        }

        if (Storage::disk('public')->exists($cleanPath)) {
            Storage::disk('public')->delete($cleanPath);
        }
    }

    public function updateDetails(Request $request)
    {

        $validator = Validator::make($request->all(), [
            "bio"           => 'nullable|string|max:255',
            "username"       => 'nullable|string|max:255|unique:users,username',
            "email"      => 'nullable|string|max:255|unique:users,email',
            "name"        => 'nullable|string|max:255',
            "date_of_birth"     => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();

        $update = $user->update([
            "bio"           => $request->bio ?? $user->bio,
            "username"       => $request->username ?? $user->username,
            "email"      => $request->email ?? $user->email,
            "dob"        => $request->date_of_birth ?? $user->dob,
            "name"        => $request->name ?? $user->name,
        ]);

        if ($update) {
            return $this->ok('Profile info updated successfully', $user, 200);
        } else {
            return $this->error('Profile info not found', 500);
        }
    }

    public function accountDelete(Request $request)
    {
        $user = Auth::user();

        $user->delete();

        return $this->ok('Account deleted successfully', [], 200);
    }

}
