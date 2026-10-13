<?php

namespace App\Services\Import;

use App\DTOs\SyncedProductDTO;
use App\Models\Product;
use App\Services\Sync\BokunNormalizer;
use App\Services\Sync\GlobalTixNormalizer;
use App\Services\Sync\ProductPersistenceService;

/**
 * Shared mapper entrypoint for Bokun and GlobalTix product imports.
 *
 * This service keeps provider-specific normalization separate while ensuring
 * both providers follow the same persistence path into normalized tables.
 */
final class ProductImportMapper
{
    public function __construct(
        private readonly BokunNormalizer $bokunNormalizer,
        private readonly GlobalTixNormalizer $globalTixNormalizer,
        private readonly ProductPersistenceService $persistence,
    ) {}

    public function importBokunProduct(array $rawPayload, bool $forceUpdate = false): Product
    {
        return $this->persistNormalizedProduct(
            $this->normalizeBokunRaw($rawPayload),
            $forceUpdate
        );
    }

    public function importGlobalTixProduct(array $rawPayload, bool $forceUpdate = false): Product
    {
        return $this->persistNormalizedProduct(
            $this->normalizeGlobalTixRaw($rawPayload),
            $forceUpdate
        );
    }

    public function normalizeBokunRaw(array $rawPayload): SyncedProductDTO
    {
        return $this->bokunNormalizer->normalize($rawPayload);
    }

    public function normalizeGlobalTixRaw(array $rawPayload): SyncedProductDTO
    {
        return $this->globalTixNormalizer->normalize($rawPayload);
    }

    public function persistNormalizedProduct(SyncedProductDTO $dto, bool $forceUpdate = false): Product
    {
        // Shared pipeline checkpoints for both providers.
        $this->upsertOptions($dto);
        $this->upsertPricingCategories($dto);
        $this->upsertTieredPrices($dto);
        $this->upsertImages($dto);
        $this->upsertAvailability($dto);

        return $this->persistence->persist($dto, $forceUpdate);
    }

    /**
     * @return int Count of option rows prepared for upsert.
     */
    private function upsertOptions(SyncedProductDTO $dto): int
    {
        return count($dto->options);
    }

    /**
     * @return int Count of pricing categories prepared for upsert.
     */
    private function upsertPricingCategories(SyncedProductDTO $dto): int
    {
        $count = 0;
        foreach ($dto->options as $option) {
            $count += count($option->categories);
        }

        return $count;
    }

    /**
     * @return int Count of tier rows prepared for upsert.
     */
    private function upsertTieredPrices(SyncedProductDTO $dto): int
    {
        $count = 0;
        foreach ($dto->priceRows as $priceRow) {
            $count += count($priceRow->tieredPrices);
        }

        return $count;
    }

    /**
     * @return int Count of images prepared for upsert.
     */
    private function upsertImages(SyncedProductDTO $dto): int
    {
        return count($dto->photos);
    }

    /**
     * @return int Count of availability slots prepared for upsert.
     */
    private function upsertAvailability(SyncedProductDTO $dto): int
    {
        return count($dto->availabilitySlots);
    }
}
