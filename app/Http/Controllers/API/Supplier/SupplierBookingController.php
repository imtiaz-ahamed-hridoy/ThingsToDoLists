<?php

namespace App\Http\Controllers\API\Supplier;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\BookingInfoRepositoryInterface;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\DB;

class SupplierBookingController extends Controller
{
    use ApiResponse;

    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private BookingInfoRepositoryInterface $bookingInfoRepository
    ) {}

    /**
     * Get Paginated Bookings for Vendor
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        if (!in_array($user->role, ['supplier', 'Vendor'], true) || !$user->vendor) {
            return $this->error('Unauthorized access.', 403);
        }

        $vendorId = $user->vendor->id;
        $vendorProducts = $this->productRepository->query()->where('vendor_id', $vendorId)->get(['id', 'product_api_id', 'title', 'thumbnail']);
        $productIds = $vendorProducts->pluck('id')->values()->toArray();
        $productApiIds = $vendorProducts->pluck('product_api_id')->filter()->values()->toArray();

        if (empty($productIds) && empty($productApiIds)) {
            return $this->success('Bookings retrieved successfully.', [
                'current_page' => 1,
                'data' => [],
                'last_page' => 1,
                'total' => 0
            ]);
        }

        $query = $this->bookingInfoRepository->query()->where(function ($query) use ($vendorId, $productIds, $productApiIds): void {
            $query->where('vendor_id', $vendorId);

            if (! empty($productIds)) {
                $query->orWhereIn('product_id', $productIds)
                    ->orWhereHas('details', fn ($q) => $q->whereIn('product_id', $productIds));
            }

            if (! empty($productApiIds)) {
                $query->orWhereHas('details', function ($q) use ($productApiIds) {
                    $this->whereDetailMatchesAnyProduct($q, $productApiIds);
                });
            }
        })->with(['details', 'product']);

        // Filter by specific product filter if provided
        if ($request->filled('product_id')) {
            // Note: product_id here refers to our local Product ID
            // but the UI image says "Filter by Product". So let's find the product_api_id
            $filterProduct = $this->productRepository->query()->where('id', $request->product_id)->where('vendor_id', $vendorId)->first();
            if ($filterProduct) {
                $query->where(function ($query) use ($filterProduct): void {
                    $query->where('product_id', $filterProduct->id)
                        ->orWhereHas('details', fn ($q) => $q->where('product_id', $filterProduct->id));

                    if ($filterProduct->product_api_id) {
                        $query->orWhereHas('details', function ($q) use ($filterProduct) {
                            $this->whereDetailMatchesProduct($q, $filterProduct->product_api_id);
                        });
                    }
                });
            }
        }

        // Filter by Reference code
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('booking_reference', 'LIKE', "%{$search}%")
                  ->orWhere('customer_name', 'LIKE', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page', 10);
        $bookings = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Map items matches UI
        $bookings->getCollection()->transform(function ($booking) use ($productApiIds) {
            $matchedDetail = $booking->details->first(function ($detail) use ($productApiIds) {
                $activityId = data_get($detail->provider_raw_data, 'activityId') ?? data_get($detail->provider_raw_data, 'productBookings.0.activityId');
                return $detail->product_id || in_array($activityId, $productApiIds);
            });

            // Find local product to get thumbnail and title
            $localProduct = $booking->product;
            if (!$localProduct && $matchedDetail?->product_id) {
                $localProduct = $this->productRepository->find((int) $matchedDetail->product_id);
            }

            if (!$localProduct && $matchedDetail) {
                $activityId = data_get($matchedDetail->provider_raw_data, 'activityId') ?? data_get($matchedDetail->provider_raw_data, 'productBookings.0.activityId');
                $localProduct = $this->productRepository->query()->where('product_api_id', $activityId)->first();
            }

            return [
                'id' => $booking->id,
                'product_title' => $localProduct ? $localProduct->title : ($matchedDetail ? $matchedDetail->title : 'Tour [No Title]'),
                'product_thumbnail' => $localProduct ? $localProduct->thumbnail : null,
                'reference_code' => $booking->booking_reference,
                'status' => $booking->status, // REVERVED, PAID, CONFIRMED, COMPLETED
                'failure_reason' => $booking->failure_reason,
                'admin_status' => $booking->admin_status,
                'created_at' => $booking->created_at->toIso8601String()
            ];
        });

        return $this->success('Bookings retrieved successfully.', $bookings);
    }

    private function whereDetailMatchesAnyProduct($query, array $productApiIds): void
    {
        $query->where(function ($jsonQuery) use ($productApiIds) {
            if ($this->usesMysqlJsonSyntax()) {
                $jsonQuery
                    ->whereIn(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(provider_raw_data, '$.activityId'))"), $productApiIds)
                    ->orWhereIn(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(provider_raw_data, '$.productBookings[0].activityId'))"), $productApiIds);

                return;
            }

            $jsonQuery
                ->whereIn('provider_raw_data->activityId', $productApiIds)
                ->orWhereIn('provider_raw_data->productBookings->0->activityId', $productApiIds);
        });
    }

    private function whereDetailMatchesProduct($query, string $productApiId): void
    {
        $query->where(function ($jsonQuery) use ($productApiId) {
            if ($this->usesMysqlJsonSyntax()) {
                $jsonQuery
                    ->where(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(provider_raw_data, '$.activityId'))"), $productApiId)
                    ->orWhere(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(provider_raw_data, '$.productBookings[0].activityId'))"), $productApiId);

                return;
            }

            $jsonQuery
                ->where('provider_raw_data->activityId', $productApiId)
                ->orWhere('provider_raw_data->productBookings->0->activityId', $productApiId);
        });
    }

    private function usesMysqlJsonSyntax(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
}
