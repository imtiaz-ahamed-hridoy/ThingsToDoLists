<?php

namespace App\Services\Contracts;

interface ProductSyncInterface
{
    /**
     * Import or update a single product by its API ID.
     *
     * @param string $platform Either 'globaltix' or 'bokun'
     * @param string $productApiId The ID from the provider
     * @param bool $forceUpdate Whether to force an update even if unchanged
     * @return array
     */
    public function importOrUpdateByApiId(string $platform, string $productApiId, bool $forceUpdate = false): array;

    /**
     * Import multiple products from a selection list of API IDs.
     *
     * @param array $productApiIds
     * @return array Summary of the operation
     */
    public function importFromTempSelection(array $productApiIds): array;
}
