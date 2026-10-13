<?php

namespace App\Http\Controllers\API\Supplier;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Traits\ApiResponse;

class SupplierReviewController extends Controller
{
    use ApiResponse;

    public function __construct(
        private ReviewRepositoryInterface $reviewRepository
    ) {}

    /**
     * Get paginated reviews for the vendor's products
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        if (!in_array($user->role, ['supplier', 'Vendor'], true) || !$user->vendor) {
            return $this->error('Unauthorized access.', 403);
        }

        // Fetch reviews where product->vendor_id matches
        $vendorId = $user->vendor->id;
        
        // Eager load product to avoid N+1 and properly format
        $query = $this->reviewRepository->query()->with('product')->whereHas('product', function ($q) use ($vendorId) {
            $q->where('vendor_id', $vendorId);
        });

        // Filter by Product ID (Dropdown in UI)
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // Filter by Rating (Star Buttons in UI)
        if ($request->filled('rating')) {
            $query->where('ratings', $request->rating);
        }

        // Search in comments
        if ($request->filled('search')) {
            $query->where('comment', 'LIKE', "%{$request->search}%");
        }

        $perPage = $request->input('per_page', 10);
        $reviews = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $reviews->getCollection()->transform(function ($review) {
            return [
                'id' => $review->id,
                'product_title' => $review->product ? ($review->product->title ?: 'Tour [No Title]') : 'Unknown Product',
                'product_thumbnail' => $review->product ? $review->product->thumbnail : null,
                'ratings' => $review->ratings,
                'comment' => $review->comment,
                'status' => $review->status,
                'created_at' => $review->created_at ? $review->created_at->format('d-m-Y') : null,
            ];
        });

        return $this->success('Reviews retrieved successfully.', $reviews);
    }
}
