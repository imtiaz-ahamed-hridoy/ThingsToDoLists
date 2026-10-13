<?php

namespace App\Repositories\Bokun;

use App\Services\Bokun\BokunClient;
use Illuminate\Support\Facades\Log;

class ActivitySearchRepository
{
    private BokunClient $client;

    public function __construct(BokunClient $client)
    {
        $this->client = $client;
    }

    /**
     * Executes the search payload against the Bokun Client.
     *
     * @param array $payload
     * @param string $language
     * @param string $currency
     * @return array
     */
    public function search(array $payload, string $language, string $currency): array
    {
        try {
            $response = $this->client->search($payload, $language, $currency);

            if (isset($response['status']) && $response['status'] === false) {
                Log::warning('Bokun Search Repository Warning', [
                    'payload' => $payload,
                    'response' => $response,
                ]);
            }

            return $response;
        } catch (\Throwable $e) {
            Log::error('Bokun Search Repository Error', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
            throw $e;
        }
    }
}
