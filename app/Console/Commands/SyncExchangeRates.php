<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Currency;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class SyncExchangeRates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'currency:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync daily exchange rates from open.er-api.com (Base: EUR)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Fetching exchange rates...');

        // Using ExchangeRate-API open free endpoint
        $response = Http::withoutVerifying()->get('https://open.er-api.com/v6/latest/EUR');

        if ($response->failed()) {
            $this->error('Failed to fetch exchange rates.');
            return;
        }

        $data = $response->json();

        if (!isset($data['rates']) || $data['result'] !== 'success') {
            $this->error('Invalid response from exchange rate API.');
            return;
        }

        $rates = $data['rates'];
        
        // Define common symbols for better UI formatting
        $symbols = [
            'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'JPY' => '¥',
            'AUD' => 'A$', 'CAD' => 'C$', 'CHF' => 'CHF', 'CNY' => '¥',
            'INR' => '₹', 'SGD' => 'S$', 'AED' => 'AED', 'SAR' => 'SAR',
        ];

        $updatedCount = 0;

        foreach ($rates as $code => $rate) {
            Currency::updateOrCreate(
                ['code' => $code],
                [
                    'exchange_rate' => $rate,
                    'symbol' => $symbols[$code] ?? $code,
                    'is_active' => true,
                ]
            );
            $updatedCount++;
        }

        // Clear the cache for the currency service
        Cache::forget('currency_exchange_rates');
        Cache::forget('active_currencies');

        $this->info("Successfully synced {$updatedCount} exchange rates!");
    }
}
