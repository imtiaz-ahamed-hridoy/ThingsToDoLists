<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class FetchRawProductData extends Command
{
    protected $signature = 'products:fetch-raw';
    protected $description = 'Fetch raw product data from API for analysis';

    public function handle()
    {
        $apiUrl = 'https://api.example.com/api/v1/product-raw-data';
        $outputDir = base_path('raw_responses');

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        $bokunIds = ['933410','933411','642031','642032','642034','396289','87','88','128','1018','2058','500299','500300','1038845','1039043','1055959','868155','868223','256553','256554'];
        $globaltixIds = ['76704','87598','87031','87497','87593','87402','87427','87417','87556','83810','83867','90357','90286','81997','82005','83884','83683','88441','88444','88446','88445','88394','88449'];

        $this->info("=== Fetching Bokun Products ===");
        foreach ($bokunIds as $id) {
            $this->fetchAndSave($apiUrl, $id, 'bokun', $outputDir);
        }

        $this->info("\n=== Fetching GlobalTix Products ===");
        foreach ($globaltixIds as $id) {
            $this->fetchAndSave($apiUrl, $id, 'globaltix', $outputDir);
        }

        $this->info("\nDone! Files saved to: {$outputDir}");
    }

    private function fetchAndSave(string $url, string $productApiId, string $platform, string $outputDir): void
    {
        try {
            $response = Http::timeout(60)->post($url, [
                'product_api_id' => $productApiId,
                'platform' => $platform,
                'language' => 'en',
                'currency' => 'EUR',
            ]);

            $filename = "{$outputDir}/{$platform}_{$productApiId}.json";
            file_put_contents($filename, $response->body());
            $this->info("  Saved {$platform}_{$productApiId}.json ({$response->status()}) - " . strlen($response->body()) . " bytes");
        } catch (\Exception $e) {
            $this->error("  ERROR fetching {$platform}_{$productApiId}: {$e->getMessage()}");
        }
    }
}
