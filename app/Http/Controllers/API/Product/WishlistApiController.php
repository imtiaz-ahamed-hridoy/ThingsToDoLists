<?php

namespace App\Http\Controllers\API\Product;

use App\Http\Controllers\Controller;
use App\Services\Product\WishlistService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WishlistApiController extends Controller
{
    public function __construct(
        private readonly WishlistService $wishlistService
    ) {}

    public function index(Request $request)
    {
        $userId = Auth::guard('sanctum')->id();
        $guestId = $request->header('X-Guest-Wishlist');

        if (!$userId && !$guestId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Please provide an authentication token or an X-Guest-Wishlist header.'
            ], 401);
        }

        $wishlistProducts = $this->wishlistService->getWishlist($userId, $guestId);

        return response()->json([
            'success' => true,
            'data' => \App\Http\Resources\ProductListResource::collection($wishlistProducts)
        ]);
    }

    public function toggle(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id'
        ]);

        $userId = Auth::guard('sanctum')->id();
        $guestId = $request->header('X-Guest-Wishlist');

        if (!$userId && !$guestId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Please provide an authentication token or an X-Guest-Wishlist header.'
            ], 401);
        }

        $added = $this->wishlistService->toggleWishlist($userId, $guestId, $request->product_id);

        return response()->json([
            'success' => true,
            'message' => $added ? 'Product added to wishlist.' : 'Product removed from wishlist.',
            'status' => $added ? 'added' : 'removed'
        ]);
    }
}
