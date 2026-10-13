<?php

namespace App\Services;

use App\Models\DestinationHighlight;
use App\Repositories\Contracts\CityRepositoryInterface;
use App\Repositories\Contracts\DestinationHighlightRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DestinationHighlightService
{
    public function __construct(
        private DestinationHighlightRepositoryInterface $repository,
        private CityRepositoryInterface $cityRepository,
        private ImageUploadService $imageUploadService
    ) {}

    /**
     * Resolve a slug to a city/country record from the cities table.
     * Returns ['city' => City|null, 'isCity' => bool]
     */
    public function resolveDestinationBySlug(string $slug): array
    {
        $decodedSlug = urldecode(trim($slug));
        $slugAsName  = str_replace('-', ' ', $decodedSlug);

        // 1. Try to find a city by slug
        $city = $this->cityRepository->findBySlug($slug)
                ?? $this->cityRepository->findBySlug($decodedSlug);

        if ($city) {
            return ['city' => $city, 'isCity' => true];
        }

        // 2. Try city_name match
        $city = $this->cityRepository->findByCityNameLike($slugAsName)
                ?? $this->cityRepository->findByCityNameLike($decodedSlug);

        if ($city) {
            return ['city' => $city, 'isCity' => true];
        }

        // 3. Try country_slug match
        $city = $this->cityRepository->findByCountrySlug($slug)
                ?? $this->cityRepository->findByCountrySlug($decodedSlug);

        if ($city) {
            return ['city' => $city, 'isCity' => false];
        }

        // 4. Try country_name match
        $city = $this->cityRepository->findByCountryNameLike($slugAsName)
                ?? $this->cityRepository->findByCountryNameLike($decodedSlug);

        if ($city) {
            return ['city' => $city, 'isCity' => false];
        }

        return ['city' => null, 'isCity' => true];
    }

    /**
     * Get all active highlights for a destination slug.
     */
    public function getHighlightsBySlug(string $slug): array
    {
        $resolved = $this->resolveDestinationBySlug($slug);

        if (!$resolved['city']) {
            return ['resolved' => null, 'highlights' => collect()];
        }

        $city   = $resolved['city'];
        $isCity = $resolved['isCity'];

        if ($isCity) {
            $cityIds = $this->cityRepository->pluckIdsByCityName($city->city_name);
            $highlights = $this->repository->getActiveHighlightsByCityIds($cityIds);
        } else {
            $cityIds = $this->cityRepository->pluckIdsByCountryName($city->country_name);
            $highlights = $this->repository->getActiveHighlightsByCountryIds($cityIds);
        }

        return [
            'resolved'   => ['city' => $city, 'isCity' => $isCity],
            'highlights' => $highlights,
        ];
    }

    /**
     * Get a highlight by ID with its products.
     */
    public function getHighlightById(int $id): ?DestinationHighlight
    {
        return $this->repository->getHighlightWithProducts($id);
    }

    /**
     * Get products for a specific destination (used in admin product selector).
     */
    public function getProductsForDestination(int $cityId, string $destinationType): Collection
    {
        return $this->repository->getProductsForDestination($cityId, $destinationType);
    }

    /**
     * Create a new Destination Highlight.
     */
    public function create(array $data, array $productIds, $imageFile = null): DestinationHighlight
    {
        return DB::transaction(function () use ($data, $productIds, $imageFile) {
            $imagePath = null;
            if ($imageFile) {
                $imagePath = $this->imageUploadService->upload($imageFile, 'destination-highlights');
            }

            $highlight = $this->repository->create([
                'name'             => $data['name'],
                'destination_type' => $data['destination_type'],
                'destination_id'   => $data['destination_id'],
                'image'            => $imagePath,
                'description'      => $data['description'] ?? null,
                'status'           => $data['status'],
            ]);

            if (!empty($productIds)) {
                $this->repository->syncProducts($highlight, $productIds);
            }

            return $highlight;
        });
    }

    /**
     * Update an existing Destination Highlight.
     */
    public function update(DestinationHighlight $highlight, array $data, array $productIds, $imageFile = null): DestinationHighlight
    {
        return DB::transaction(function () use ($highlight, $data, $productIds, $imageFile) {
            $oldImagePath = $highlight->getRawOriginal('image');

            if ($imageFile) {
                $this->imageUploadService->delete($oldImagePath);
                $imagePath = $this->imageUploadService->upload($imageFile, 'destination-highlights');
            } else {
                $imagePath = $oldImagePath;
            }

            $this->repository->update($highlight, [
                'name'             => $data['name'] ?? $highlight->name,
                'destination_type' => $data['destination_type'] ?? $highlight->destination_type,
                'destination_id'   => $data['destination_id'] ?? $highlight->destination_id,
                'image'            => $imagePath,
                'description'      => $data['description'] ?? $highlight->description,
                'status'           => $data['status'] ?? $highlight->status,
            ]);

            $this->repository->syncProducts($highlight, $productIds);

            return $highlight->fresh();
        });
    }

    /**
     * Delete a Destination Highlight.
     */
    public function delete(DestinationHighlight $highlight): void
    {
        $imagePath = $highlight->getRawOriginal('image');

        $this->repository->delete($highlight);

        // Clean up the associated image file
        $this->imageUploadService->delete($imagePath);
    }
}
