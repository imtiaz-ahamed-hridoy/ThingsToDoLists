<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurrencySeeder extends Seeder
{
    /**
     * Seed common currencies with approximate USD exchange rates.
     * These will be overwritten by php artisan currency:sync with live rates.
     */
    public function run(): void
    {
        $currencies = [
            ['code' => 'USD', 'symbol' => '$',   'exchange_rate' => 1.000000],
            ['code' => 'EUR', 'symbol' => '€',   'exchange_rate' => 0.920000],
            ['code' => 'GBP', 'symbol' => '£',   'exchange_rate' => 0.790000],
            ['code' => 'JPY', 'symbol' => '¥',   'exchange_rate' => 155.000000],
            ['code' => 'AUD', 'symbol' => 'A$',  'exchange_rate' => 1.540000],
            ['code' => 'CAD', 'symbol' => 'C$',  'exchange_rate' => 1.370000],
            ['code' => 'CHF', 'symbol' => 'CHF', 'exchange_rate' => 0.900000],
            ['code' => 'CNY', 'symbol' => '¥',   'exchange_rate' => 7.240000],
            ['code' => 'INR', 'symbol' => '₹',   'exchange_rate' => 83.500000],
            ['code' => 'SGD', 'symbol' => 'S$',  'exchange_rate' => 1.350000],
            ['code' => 'AED', 'symbol' => 'AED', 'exchange_rate' => 3.670000],
            ['code' => 'SAR', 'symbol' => 'SAR', 'exchange_rate' => 3.750000],
            ['code' => 'MYR', 'symbol' => 'RM',  'exchange_rate' => 4.700000],
            ['code' => 'THB', 'symbol' => '฿',   'exchange_rate' => 35.000000],
            ['code' => 'IDR', 'symbol' => 'Rp',  'exchange_rate' => 16200.000000],
            ['code' => 'VND', 'symbol' => '₫',   'exchange_rate' => 25400.000000],
            ['code' => 'PHP', 'symbol' => '₱',   'exchange_rate' => 58.000000],
            ['code' => 'KRW', 'symbol' => '₩',   'exchange_rate' => 1370.000000],
            ['code' => 'TWD', 'symbol' => 'NT$', 'exchange_rate' => 32.500000],
            ['code' => 'HKD', 'symbol' => 'HK$', 'exchange_rate' => 7.820000],
            ['code' => 'NZD', 'symbol' => 'NZ$', 'exchange_rate' => 1.630000],
            ['code' => 'ZAR', 'symbol' => 'R',   'exchange_rate' => 18.500000],
            ['code' => 'BDT', 'symbol' => '৳',   'exchange_rate' => 110.000000],
            ['code' => 'PKR', 'symbol' => '₨',   'exchange_rate' => 278.000000],
            ['code' => 'LKR', 'symbol' => '₨',   'exchange_rate' => 305.000000],
        ];

        foreach ($currencies as $currency) {
            DB::table('currencies')->updateOrInsert(
                ['code' => $currency['code']],
                array_merge($currency, [
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
