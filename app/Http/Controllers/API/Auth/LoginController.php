<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\Auth\ChangePasswordRequest;
use App\Http\Requests\API\Auth\LoginRequest;
use App\Http\Requests\API\Auth\UpdateProfileRequest;
use App\Services\Auth\AuthFacade;
use App\Services\Product\WishlistService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    use ApiResponse;

    public AuthFacade $authFacade;
    protected WishlistService $wishlistService;

    public function __construct(AuthFacade $authFacade, WishlistService $wishlistService)
    {
        $this->authFacade = $authFacade;
        $this->wishlistService = $wishlistService;
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $response = $this->authFacade->login($request->email, $request->password);

        // After a successful login, sync any guest wishlist to the user's account
        if ($response->getStatusCode() === 200) {
            $guestId = $request->header('X-Guest-Wishlist');
            if ($guestId) {
                $user = Auth::user();
                if ($user) {
                    $this->wishlistService->syncGuestToUser($user->id, $guestId);
                }
            }
        }

        return $response;
    }

    public function logout(Request $request)
    {
        return $this->authFacade->logout($request);
    }

    public function logoutAll(Request $request)
    {
        return $this->authFacade->logoutAll($request);
    }

    public function profileUpdate(UpdateProfileRequest $request)
    {
        return $this->authFacade->updateProfile($request);
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        return $this->authFacade->changePassword($request);
    }

    public function profile()
    {
        return $this->authFacade->getProfile();
    }
}

