<?php

namespace App\Services\Provider;

use App\Services\GlobalTix\GlobalTixAuthService;
use App\Services\GlobalTix\GlobalTixProductService;
use App\Services\Bokun\BokunService;
use App\Services\GlobalTix\GlobaltixService;

class ProviderFactory
{
    public static function make(string $provider)
    {
        return match (strtolower($provider)) {
            'bokun' => app(BokunService::class),
            'globaltix' => app(GlobaltixService::class),
            default => throw new \InvalidArgumentException("Unknown provider: {$provider}"),
        };
    }
}
