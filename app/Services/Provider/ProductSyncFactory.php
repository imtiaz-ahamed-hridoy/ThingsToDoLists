<?php

namespace App\Services\Provider;

use App\Services\Contracts\ProductSyncInterface;
use App\Services\Bokun\BokunProductSyncService;
use App\Services\GlobalTix\GlobalTixProductSyncService;

class ProductSyncFactory
{
    public static function make(string $provider): ProductSyncInterface
    {
        return match (strtolower($provider)) {
            'bokun' => app(BokunProductSyncService::class),
            'globaltix' => app(GlobalTixProductSyncService::class),
            default => throw new \InvalidArgumentException("Unknown sync provider: {$provider}"),
        };
    }
}
