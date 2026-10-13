<?php

namespace App\Repositories\Contracts;

use App\Models\OptionPricingCategory;
use Illuminate\Database\Eloquent\Collection;

interface OptionPricingCategoryRepositoryInterface extends RepositoryInterface
{
    public function getByOption(int $productOptionId): Collection;

    public function getDefault(int $productOptionId): ?OptionPricingCategory;

    public function getByCategoryCode(string $categoryCode): Collection;
}
