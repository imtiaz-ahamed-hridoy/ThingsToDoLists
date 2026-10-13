<?php

namespace App\Http\Controllers\API\Supplier;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Traits\ApiResponse;

class SupplierProductController extends Controller
{
    use ApiResponse;

    public function __construct(
        private ProductRepositoryInterface $productRepository
    ) {}

    /**
     * Get paginated vendor products 
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        if (!in_array($user->role, ['supplier', 'Vendor'], true) || !$user->vendor) {
            return $this->error('Unauthorized access.', 403);
        }

        $query = $this->productRepository->query()->where('vendor_id', $user->vendor->id);

        // Filter by Search (Title/Reference)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('product_api_id', 'LIKE', "%{$search}%");
            });
        }

        // Filter by Status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->input('per_page', 10);
        $products = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $products->getCollection()->transform(function ($product) {
            $statusLabels = [
                'draft' => 'Not submitted',
                'in_review' => 'In review',
                'active' => 'Bookable',
                'deactivated' => 'Deactivate',
                'no_availability' => 'No Availability',
            ];

            return [
                'id' => $product->id,
                'title' => $product->title ?: 'Tour [No Title]',
                'thumbnail' => $product->thumbnail,
                'reference_code' => $product->reference_code ?: $product->product_api_id,
                'status' => $product->status,
                'display_status' => $statusLabels[$product->status] ?? ucfirst($product->status),
                'is_draft' => (bool) $product->is_draft,
            ];
        });

        return $this->success('Products retrieved successfully.', $products);
    }

    /**
     * Delete / Deactivate a vendor product
     */
    public function destroy($id)
    {
        $user = auth()->user();
        if (!in_array($user->role, ['supplier', 'Vendor'], true) || !$user->vendor) {
            return $this->error('Unauthorized access.', 403);
        }

        $product = $this->productRepository->query()->where('vendor_id', $user->vendor->id)->where('id', $id)->first();
        
        if (!$product) {
            return $this->error('Product not found.', 404);
        }

        // Soft delete or just update status to INACTIVE depending on system logic.
        // The prompt says "Delete" so we will delete it. 
        $product->delete();

        return $this->success('Product deleted successfully.');
    }
}
