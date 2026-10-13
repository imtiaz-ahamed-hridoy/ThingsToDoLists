<?php

namespace App\Services\Bokun;

class BokunClient
{
    private BokunService $bokunService;

    public function __construct(BokunService $bokunService)
    {
        $this->bokunService = $bokunService;
    }

    /**
     * Perform a search query against Bokun.
     *
     * @param array $payload The built payload
     * @param string $language
     * @param string $currency
     * @return array
     */
    public function search(array $payload, string $language = 'EN', string $currency = 'EUR'): array
    {
        $query = [
            'lang' => strtoupper($language),
            'currency' => strtoupper($currency)
        ];

        return $this->bokunService->makeRequest('POST', '/activity.json/search', $query, $payload);
    }
}
