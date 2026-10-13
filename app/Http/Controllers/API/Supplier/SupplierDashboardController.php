<?php

namespace App\Http\Controllers\API\Supplier;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\SystemSettingRepositoryInterface;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class SupplierDashboardController extends Controller
{
    use ApiResponse;

    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private SystemSettingRepositoryInterface $systemSettingRepository
    ) {}

    /**
     * Supplier Home / Dafshboard
     *
     * Returns:
     * - Supplier guide info (static)
     * - List of draft (incomplete) products with progress percentage
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        if (!in_array($user->role, ['supplier', 'Vendor'], true) || !$user->vendor) {
            return $this->error('Unauthorized access.', 403);
        }

        $vendor = $user->vendor;

        // Fetch draft products (is_draft = true) for "Finish creating your products" (max 50)
        // Fetch draft products (is_draft = true) for "Finish creating your products" (max 50)
        $draftProducts = $this->productRepository->query()
            ->where('vendor_id', $vendor->id)
            ->where('is_draft', true)
            ->select(['id', 'title', 'thumbnail', 'created_at', 'wizard_step'])
            ->orderBy('updated_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($product) {
                return [
                    'id'            => $product->id,
                    'title'         => $product->title ?: 'Tour [No Title]',
                    'thumbnail'     => $product->thumbnail,
                    'started_on'    => $product->created_at ? $product->created_at->format('l, F jS, Y') : null,
                    'wizard_step'   => $product->wizard_step,
                    'progress'      => $this->calculateProgress($product),
                ];
            });

        // Count published products
        $publishedCount = $this->productRepository->query()
            ->where('vendor_id', $vendor->id)
            ->where('is_draft', false)
            ->count();

        return $this->success('Dashboard loaded successfully.', [
            'supplier_guide' => $this->supplierGuideData($this->systemSettingRepository->first()),
            'draft_products'    => $draftProducts,
            'published_count'   => $publishedCount,
            'vendor_name'       => $vendor->name,
        ]);
    }

    /**
     * Calculate the wizard completion progress percentage.
     * Total steps = 16 (matching the product creation wizard).
     */
    private function calculateProgress(Product $product): int
    {
        $totalSteps = 16;
        $currentStep = min((int) $product->wizard_step, $totalSteps);

        return (int) round(($currentStep / $totalSteps) * 100);
    }

    private function supplierGuideData(?SystemSetting $setting): array
    {
        $fallbackUrl = url('storage/supplier-guide.png');
        $usesLinkOrIframe = ! empty($setting?->supplier_guide_link_or_iframe);
        $guideValue = $usesLinkOrIframe ? $setting->supplier_guide_link_or_iframe : ($setting?->supplier_guide_media ?: $fallbackUrl);

        return [
            'title' => $setting?->supplier_guide_title ?: 'Supplier guide ThingToDoLists.',
            'description' => $setting?->supplier_guide_description ?: 'Explore Enhanced Features, Upgrades, and Valuable Insights with ThingToDoLists - Streamline Your Operations and Shine!',
            'link_or_iframe' => $guideValue,
            'media_url' => $setting?->supplier_guide_media,
            'media_type' => $setting?->supplier_guide_media_type,
            'content_type' => $this->resolveGuideContentType($setting, $guideValue, $usesLinkOrIframe),
        ];
    }

    private function resolveGuideContentType(?SystemSetting $setting, string $guideValue, bool $usesLinkOrIframe): string
    {
        if ($usesLinkOrIframe) {
            return str_contains(strtolower($guideValue), '<iframe') ? 'iframe' : 'link';
        }

        if ($setting?->supplier_guide_media_type) {
            return $setting->supplier_guide_media_type;
        }

        if (preg_match('/\.(jpg|jpeg|png|gif|svg|webp)(\?.*)?$/i', $guideValue)) {
            return 'image';
        }

        if (preg_match('/\.(mp4|webm|mov)(\?.*)?$/i', $guideValue)) {
            return 'video';
        }

        return 'link';
    }
}
