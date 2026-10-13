<?php

namespace App\Http\Controllers\API\Supplier;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;

class SupplierProfileController extends Controller
{
    use ApiResponse;

    /**
     * GET /supplier/profile
     * Returns the supplier profile dropdown data matching Image 4.
     */
    public function show()
    {
        $user = auth()->user();
        if (!in_array($user->role, ['supplier', 'Vendor'], true) || !$user->vendor) {
            return $this->error('Unauthorized access.', 403);
        }

        $vendor = $user->vendor;

        return $this->success('Profile loaded.', [
            'name'               => $user->name,
            'email'              => $user->email,
            'avatar'             => $user->avatar,
            'supplier_id'        => $vendor->supplier_id ?? $vendor->id,
            'currency'           => $vendor->currency ?? 'EUR',
            'preferred_language' => $vendor->preferred_language ?? 'en',
            'help_url'           => null,
        ]);
    }

    /**
     * PUT /supplier/profile
     * Update supplier profile settings (currency, language, avatar).
     */
    public function update(Request $request)
    {
        $user = auth()->user();
        if (!in_array($user->role, ['supplier', 'Vendor'], true) || !$user->vendor) {
            return $this->error('Unauthorized access.', 403);
        }

        $request->validate([
            'currency'           => 'nullable|string|max:10',
            'preferred_language' => 'nullable|string|max:10',
            'name'         => 'nullable|string|max:255',
            ''          => 'nullable|string|max:255',
        ]);

        $vendor = $user->vendor;

        if ($request->has('currency')) {
            $vendor->currency = $request->currency;
        }
        if ($request->has('preferred_language')) {
            $vendor->preferred_language = $request->preferred_language;
        }
        $vendor->save();

        if ($request->has('name')) {
            $user->name = $request->name;
        }
        if ($request->has('')) {
            $user->name = $request->name;
        }
        $user->save();

        return $this->success('Profile updated successfully.', [
            'name'               => $user->fresh()->name,
            'supplier_id'        => $vendor->supplier_id ?? $vendor->id,
            'currency'           => $vendor->currency,
            'preferred_language' => $vendor->preferred_language,
        ]);
    }

    /**
     * POST /supplier/profile/avatar
     * Upload a new profile picture.
     */
    public function uploadAvatar(Request $request)
    {
        $user = auth()->user();
        if (!in_array($user->role, ['supplier', 'Vendor'], true) || !$user->vendor) {
            return $this->error('Unauthorized access.', 403);
        }

        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $path = $request->file('avatar')->store('avatars', 'public');

        $user->update(['avatar' => $path]);

        return $this->success('Avatar updated successfully.', [
            'avatar' => $user->avatar,
        ]);
    }
}

