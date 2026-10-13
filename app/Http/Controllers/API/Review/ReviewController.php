<?php

namespace App\Http\Controllers\API\Review;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Traits\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReviewController extends Controller
{
    use ApiResponse, AuthorizesRequests;

    public function __construct(
        private ReviewRepositoryInterface $reviewRepository
    ) {}

    public function index(Request $request)
    {
        $query = $this->reviewRepository->query()
            ->with($this->reviewRelations())
            ->where('status', 'Active');

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        $perPage = (int) $request->input('per_page', 5);

        // Summary stats
        $total = (clone $query)->count();
        $average = $total ? round((float) (clone $query)->avg('ratings'), 1) : 0.0;

        $counts = (clone $query)
            ->select('ratings', DB::raw('count(*) as count'))
            ->groupBy('ratings')
            ->pluck('count', 'ratings')
            ->toArray();

        $ratingBreakdown = [];
        for ($i = 5; $i >= 1; $i--) {
            $ratingBreakdown[$i] = (int) ($counts[$i] ?? 0);
        }

        $reviews = $query->latest()->paginate($perPage);

        $reviewsArray = ReviewResource::collection($reviews->getCollection())->resolve($request);

        $payload = [
            'overall_rating' => $average,
            'ratings_count' => (int) $total,
            'rating_breakdown' => $ratingBreakdown,
            'reviews' => $reviewsArray,
            'pagination' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ];

        return $this->success('Reviews retrieved successfully', $payload);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->reviewRules());
        $imagePaths = [];

        $user = $request->user();
        if (! $user) {
            return $this->error('Unauthenticated', 401);
        }

        $review = $this->reviewRepository->create([
            'user_id' => $user->id,
            'product_id' => $validated['product_id'],
            'ratings' => $validated['ratings'],
            'comment' => $validated['comment'],
            'images' => [],
            'status' => 'Active',
        ]);

        $uploadedImages = $this->reviewUploadFiles($request);
        if ($uploadedImages !== []) {
            $imagePaths = $this->storeReviewImages($review, $uploadedImages);
            $review->update(['images' => $imagePaths]);
        }

        $review->load($this->reviewRelations());

        return $this->success('Review successfully stored', (new ReviewResource($review))->resolve($request));
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate($this->reviewRules());

        $review = $this->reviewRepository->find($id);

        if (! $review) {
            return $this->error('Review not found!', 404);
        }

        $this->authorize('update', $review);
        // If legacy review has no owner, attach current authenticated user.
        // Otherwise preserve the existing owner (do not allow changing owner).
        $payload = [
            'user_id' => $review->user_id ?? $request->user()?->id ?? null,
            'product_id' => $validated['product_id'],
            'ratings' => $validated['ratings'],
            'comment' => $validated['comment'],
            'status' => $review->status ?: 'Active',
        ];

        if ($this->shouldSyncImages($request)) {
            $payload['images'] = $this->syncReviewImages(
                $review,
                $this->retainedReviewImages($review, $validated['existing_images'] ?? null),
                $this->reviewUploadFiles($request)
            );
        }

        $review->update($payload);

        $review->load($this->reviewRelations());

        return $this->success('Review updated successfully!', (new ReviewResource($review))->resolve($request));
    }

    public function destroy($id)
    {
        $review = $this->reviewRepository->find($id);

        if (! $review) {
            return $this->error('Review not found!', 404);
        }

        $this->authorize('delete', $review);
        $this->deleteReviewImages($review->images ?? []);
        $this->reviewRepository->delete($review);

        return $this->success('Review deleted successfully', $review);
    }

    private function reviewRules(): array
    {
        return [
            // user id is derived from authenticated user; do not accept in payload
            'product_id' => 'required|integer|exists:products,id',
            'ratings' => 'required|integer|min:1|max:5',
            'comment' => 'required|string',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg,webp|max:5120',
            // backward-compat aliases (common misspellings)
            'upload_images' => 'nullable|array',
            'upload_images.*' => 'image|mimes:jpeg,png,jpg,gif,svg,webp|max:5120',
            'uplod_images' => 'nullable|array',
            'uplod_images.*' => 'image|mimes:jpeg,png,jpg,gif,svg,webp|max:5120',
            'existing_images' => 'nullable|array',
            'existing_images.*' => 'string',
            'company' => 'nullable|string|max:255',
        ];
    }

    private function resolveUserId(Request $request, array $validated, ?Review $review = null): ?int
    {
        // For creation, always use authenticated user id
        if ($review === null) {
            return $request->user()?->id ?? null;
        }

        // For updates, preserve existing owner unless explicitly changed by admin elsewhere
        return $review->user_id ?? null;
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function reviewUploadFiles(Request $request): array
    {
        $files = [];

        // Preferred field: images (allows multiple files with same key)
        $candidates = ['images', 'upload_images', 'uplod_images'];

        foreach ($candidates as $key) {
            $found = $request->file($key);
            if ($found === null || $found === []) {
                continue;
            }

            if (is_array($found)) {
                foreach ($found as $f) {
                    if ($f instanceof UploadedFile) {
                        $files[] = $f;
                    }
                }
                continue;
            }

            if ($found instanceof UploadedFile) {
                $files[] = $found;
            }
        }

        return array_values($files);
    }

    /**
     * @param  array<int, UploadedFile>  $images
     * @return array<int, string>
     */
    private function storeReviewImages(Review $review, array $images): array
    {
        $paths = [];

        foreach ($images as $image) {
            $filename = sprintf('%s-%s.%s', now()->format('YmdHis'), Str::uuid(), $image->getClientOriginalExtension());
            $paths[] = $image->storeAs("uploads/reviews/{$review->id}", $filename, 'public');
        }

        return $paths;
    }

    private function shouldSyncImages(Request $request): bool
    {
        return $request->hasFile('images')
            || $request->hasFile('uplod_images')
            || $request->hasFile('upload_images')
            || $request->exists('existing_images');
    }

    /**
     * @param  array<int, string>|null  $requestedImages
     * @return array<int, string>
     */
    private function retainedReviewImages(Review $review, ?array $requestedImages): array
    {
        $currentImages = collect($review->images ?? [])
            ->filter(fn($image) => is_string($image) && $image !== '')
            ->values();

        if ($requestedImages === null) {
            return $currentImages->all();
        }

        $requestedLookup = collect($requestedImages)
            ->filter(fn($image) => is_string($image) && $image !== '')
            ->map(fn($image) => $this->normalizeStoredImagePath($image))
            ->filter()
            ->flip();

        return $currentImages
            ->filter(fn($image) => $requestedLookup->has($this->normalizeStoredImagePath($image)))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $retainedImages
     * @param  array<int, UploadedFile>  $newImages
     * @return array<int, string>
     */
    private function syncReviewImages(Review $review, array $retainedImages, array $newImages): array
    {
        $existingImages = collect($review->images ?? [])
            ->filter(fn($image) => is_string($image) && $image !== '')
            ->values();

        $retainedLookup = collect($retainedImages)->flip();
        $deletedImages = $existingImages
            ->reject(fn($image) => $retainedLookup->has($image))
            ->values()
            ->all();

        $this->deleteReviewImages($deletedImages);

        return array_values(array_merge($retainedImages, $this->storeReviewImages($review, $newImages)));
    }

    /**
     * @param  array<int, string>  $images
     */
    private function deleteReviewImages(array $images): void
    {
        foreach ($images as $image) {
            $path = $this->normalizeStoredImagePath($image);

            if ($path !== null && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    private function normalizeStoredImagePath(string $image): ?string
    {
        $value = trim($image);

        if ($value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            $path = parse_url($value, PHP_URL_PATH);
            $value = is_string($path) ? $path : $value;
        }

        $value = ltrim($value, '/');

        if (Str::startsWith($value, 'storage/')) {
            $value = substr($value, 8);
        }

        return $value !== '' ? $value : null;
    }

    private function reviewRelations(): array
    {
        return [
            // include avatar so review responses can show user avatar without extra queries
            'users:id,name,avatar',
            'product' => fn($query) => $query
                ->select([
                    'id',
                    'category_id',
                    'provider',
                    'platform',
                    'product_api_id',
                    'reference_code',
                    'title',
                    'slug',
                    'short_introduction',
                    'highlights_summary',
                    'description',
                    'full_description',
                    'thumbnail',
                    'image_gallery',
                    'raw_response',
                    'address_line',
                    'meeting_point_address',
                    'places',
                    'address_list',
                    'customer_visits',
                    'reviewCount',
                ])
                ->withCount('reviews as total_review_count'),
            'product.category:id,city_id,location_country,location_country_code',
            'product.category.city:id,city_name,country_name,thumbnail_image',
            'product.location:id,product_id,city,country',
            'product.locations:id,product_id,city,country,is_primary,sort_order',
            'product.productImages' => fn($query) => $query->active()->ordered()->select([
                'id',
                'product_id',
                'original_url',
                'large_url',
                'preview_url',
                'is_primary',
                'is_active',
                'sort_order',
            ]),
        ];
    }
}
