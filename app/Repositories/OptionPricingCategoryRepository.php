<?php

namespace App\Repositories;

use App\Models\OptionPricingCategory;
use App\Repositories\Contracts\OptionPricingCategoryRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class OptionPricingCategoryRepository extends BaseRepository implements OptionPricingCategoryRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new OptionPricingCategory();
    }

    public function getByOption(int $productOptionId): Collection
    {
        return $this->model->newQuery()
            ->where('product_option_id', $productOptionId)
            ->orderBy('sort_order')
            ->get();
    }

    public function getDefault(int $productOptionId): ?OptionPricingCategory
    {
        return $this->model->newQuery()
            ->where('product_option_id', $productOptionId)
            ->where('is_default', true)
            ->first();
    }

    public function getByCategoryCode(string $categoryCode): Collection
    {
        return $this->model->newQuery()
            ->where('category_code', $categoryCode)
            ->get();
    }
}
