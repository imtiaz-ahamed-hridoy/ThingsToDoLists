<?php

namespace App\Repositories;

use App\Models\City;
use App\Models\DestinationHighlight;
use App\Models\Product;
use App\Repositories\Contracts\DestinationHighlightRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Support\ManualSelectionOrder;

class DestinationHighlightRepository extends BaseRepository implements DestinationHighlightRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new DestinationHighlight();
    }

    public function getActiveHighlightsByCityIds(array $cityIds): Collection
    {
        return DestinationHighlight::active()
            ->where('destination_type', 'city')
            ->whereIn('destination_id', $cityIds)
            ->withCount('products')
            ->with('destination:id,city_name,country_name')
            ->orderBy('id')
            ->get();
    }

    public function getActiveHighlightsByCountryIds(array $cityIds): Collection
    {
        return DestinationHighlight::active()
            ->where('destination_type', 'country')
            ->whereIn('destination_id', $cityIds)
            ->withCount('products')
            ->with('destination:id,city_name,country_name')
            ->orderBy('id')
            ->get();
    }

    public function getHighlightWithProducts(int $id): ?DestinationHighlight
    {
        return DestinationHighlight::active()
            ->with([
                'destination:id,city_name,country_name,country_slug,slug',
                'products' => function ($q) {
                    $q->where('status', 'active')
                      ->where('published', true)
                      ->select([
                          'products.id',
                          'products.title',
                          'products.slug',
                          'products.thumbnail',
                          'products.from_price',
                          'products.original_price',
                          'products.default_currency',
                          'products.reviewRating',
                          'products.reviewCount',
                          'products.durationText',
                      ])
                      ->with([
                          'location:id,product_id,city,country',
                          'productImages' => fn($q) => $q->select(['id', 'product_id', 'preview_url', 'large_url', 'original_url', 'is_primary', 'sort_order'])
                                                         ->active()
                                                         ->ordered(),
                      ]);
                },
            ])
            ->find($id);
    }

    public function getProductsForDestination(int $cityId, string $destinationType): Collection
    {
        $city = City::find($cityId);
        if (!$city) {
            return collect();
        }

        $query = Product::query()
            ->select(['id', 'title', 'provider', 'platform', 'product_api_id'])
            ->where('status', 'active')
            ->where('published', true)
            ->orderBy('title');

        if ($destinationType === 'city') {
            $query->whereHas('locations', function (Builder $q) use ($city) {
                $q->where('city', 'LIKE', '%' . $city->city_name . '%');
            });
        } else {
            $query->whereHas('locations', function (Builder $q) use ($city) {
                $q->where('country', 'LIKE', '%' . $city->country_name . '%');
            });
        }

        return $query->get();
    }

    public function syncProducts(DestinationHighlight $highlight, array $productIds): void
    {
        $highlight->products()->sync(ManualSelectionOrder::syncPayload($productIds));
    }
}
