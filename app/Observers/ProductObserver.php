<?php

namespace App\Observers;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class ProductObserver
{
    public function saved(Product $product): void
    {
        $this->invalidateCategoryStats(
            $product->getOriginal('category_id'),
            $product->category_id
        );
        $this->invalidateHomePageSectionsCache($product->id);
        $this->invalidateProductCaches($product);
    }

    public function deleted(Product $product): void
    {
        $this->invalidateCategoryStats(null, $product->category_id);
        $this->invalidateHomePageSectionsCache($product->id);
        $this->invalidateProductCaches($product);
    }

    private function invalidateCategoryStats(?int $originalCategoryId, ?int $currentCategoryId): void
    {
        $categoryIdsToClear = array_unique(array_filter([
            $originalCategoryId,
            $currentCategoryId,
        ]));

        if (empty($categoryIdsToClear)) {
            return;
        }

        foreach ($categoryIdsToClear as $catId) {
            Cache::forget("category:stats:{$catId}");
        }

        Cache::forget('category:stats:all');
    }

    private function invalidateHomePageSectionsCache(int $productId): void
    {
        Cache::forget('homepage:sections:active');
        $sections = \App\Models\HomePageSection::whereJsonContains('product_ids', $productId)
            ->orWhereJsonContains('product_ids', (string) $productId)
            ->get();
            
        foreach ($sections as $section) {
            Cache::forget('homepage:section:' . $section->id);
        }
    }

    private function invalidateProductCaches(Product $product): void
    {
        Cache::forget('product_detail:' . $product->id);
        if ($product->product_api_id && $product->platform) {
            Cache::forget('product_detail:' . $product->product_api_id . '|' . $product->platform);
        }
    }
}
