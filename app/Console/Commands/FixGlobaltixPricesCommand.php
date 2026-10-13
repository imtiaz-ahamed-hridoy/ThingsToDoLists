<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Models\OptionPricingCategory;
use App\Models\ProductOption;
use App\Models\Price;
use App\Services\Sync\PriceCalculator;
use App\Services\CurrencyService;

class FixGlobaltixPricesCommand extends Command
{
    protected $signature = "globaltix:fix-prices {--id= : Specific product ID to fix} {--api-id= : Specific product API ID to fix}";
    protected $description = "Fix from_price, original_price, and option pricing categories for GlobalTix products using Adult/Child hierarchy and correct currency pricing";

    public function handle()
    {
        $count = 0;
        $currencyService = app(CurrencyService::class);
        $productId = $this->option('id');
        $productApiId = $this->option('api-id');

        $query = Product::where(function ($q) {
            $q->where("provider", "globaltix")->orWhere("platform", "globaltix");
        });

        if ($productId) {
            $query->where('id', $productId);
        }

        if ($productApiId) {
            $query->where('product_api_id', $productApiId);
        }

        $query->chunk(100, function ($products) use (&$count, $currencyService) {
            foreach ($products as $product) {
                $country = $product->location?->country ?? $product->location?->countryCode;
                $city = $product->location?->city;

                $raw = is_string($product->getRawOriginal("raw_response"))
                    ? json_decode($product->getRawOriginal("raw_response"), true)
                    : (array) ($product->raw_response->raw_data ?? $product->raw_response ?? []);

                $rawOptions = data_get($raw, 'data.activityOptions.data')
                    ?? data_get($raw, 'data.activityOptions')
                    ?? data_get($raw, 'activityOptions.data')
                    ?? data_get($raw, 'activityOptions')
                    ?? data_get($raw, 'raw_data.data.activityOptions.data')
                    ?? data_get($raw, 'raw_data.data.activityOptions')
                    ?? data_get($raw, 'raw_data.activityOptions.data')
                    ?? data_get($raw, 'raw_data.activityOptions')
                    ?? [];

                $ticketLookup = [];

                if (is_array($rawOptions) && !empty($rawOptions)) {
                    foreach ($rawOptions as $opt) {
                        if (!is_array($opt)) continue;
                        $optId = $opt['id'] ?? null;
                        foreach (($opt['ticketType'] ?? []) as $ticket) {
                            if (!is_array($ticket)) continue;
                            $ticketId = (string) ($ticket['id'] ?? '');
                            if ($ticketId === '') continue;

                            $sellingPrice = PriceCalculator::resolveGlobalTixSellingPrice($ticket, $product->default_currency ?? 'EUR', $country, $city);
                            $nettPrice    = PriceCalculator::resolveGlobalTixNettPrice($ticket, $product->default_currency ?? 'EUR', $country, $city);
                            $origPrice    = PriceCalculator::resolveGlobalTixOriginalPrice($ticket, $product->default_currency ?? 'EUR', $country, $city);
                            $recPrice     = PriceCalculator::resolveGlobalTixRecommendedPrice($ticket, $product->default_currency ?? 'EUR', $country, $city);

                            $ticketLookup[$ticketId] = [
                                'ticket'            => $ticket,
                                'optionId'          => $optId,
                                'selling_price'     => $sellingPrice,
                                'nett_price'        => $nettPrice,
                                'original_price'    => $origPrice,
                                'recommended_price' => $recPrice,
                                'currency'          => $ticket['currency'] ?? $product->default_currency ?? 'EUR',
                                'category_code'     => $ticket['name'] ?? 'PER_PAX',
                                'category_label'    => $ticket['name'] ?? 'Per Pax',
                            ];

                            // Update OptionPricingCategory in DB if exists
                            OptionPricingCategory::where('external_id', $ticketId)->update([
                                'selling_price'     => $sellingPrice,
                                'nett_price'        => $nettPrice,
                                'original_price'    => $origPrice,
                                'recommended_price' => $recPrice,
                                'merchant_price'    => $sellingPrice,
                            ]);

                            // Update Price in DB if exists
                            Price::where('ticket_type_id', $ticketId)
                                ->orWhere('pricing_category_id', function ($q) use ($ticketId) {
                                    $q->select('id')->from('option_pricing_categories')->where('external_id', $ticketId)->limit(1);
                                })
                                ->update([
                                    'selling_price'     => $sellingPrice,
                                    'nett_price'        => $nettPrice,
                                    'original_price'    => $origPrice,
                                    'recommended_price' => $recPrice,
                                ]);
                        }
                    }
                }

                // If ticketLookup has tickets, resolve product display prices from tickets
                if (!empty($ticketLookup)) {
                    $resolved = PriceCalculator::resolveProductDisplayPrices($ticketLookup, null, null, $country, $city);
                } else {
                    // Check existing option pricing categories in DB
                    $categories = OptionPricingCategory::whereIn(
                        'product_option_id',
                        ProductOption::where('product_id', $product->id)->select('id')
                    )->get();

                    // If existing category selling prices look like unscaled foreign currency (> 150 with EUR currency), convert them
                    foreach ($categories as $cat) {
                        if ($cat->selling_price > 150 && strtoupper((string) ($cat->currency ?? 'EUR')) === 'EUR') {
                            $convertedSelling = $currencyService->convertToEur($cat->selling_price, 'EUR', $country, $city) ?? $cat->selling_price;
                            $convertedOrig    = $currencyService->convertToEur($cat->original_price, 'EUR', $country, $city) ?? $cat->original_price;
                            $convertedNett    = $currencyService->convertToEur($cat->nett_price, 'EUR', $country, $city) ?? $cat->nett_price;

                            $cat->update([
                                'selling_price'     => $convertedSelling,
                                'original_price'    => $convertedOrig,
                                'nett_price'        => $convertedNett,
                                'recommended_price' => $convertedSelling,
                            ]);
                        }
                    }

                    // Reload categories after conversion
                    $categories = OptionPricingCategory::whereIn(
                        'product_option_id',
                        ProductOption::where('product_id', $product->id)->select('id')
                    )->get();

                    if ($categories->isNotEmpty()) {
                        $resolved = PriceCalculator::resolveProductDisplayPrices($categories, null, null, $country, $city);
                    } else {
                        $prices = $product->prices()->get();
                        $resolved = PriceCalculator::resolveProductDisplayPrices($prices, null, null, $country, $city);
                    }
                }

                $product->from_price = $resolved['from_price'];
                $product->original_price = $resolved['original_price'];
                $product->saveQuietly();

                $this->info("Product {$product->id} ({$product->title}): from_price={$product->from_price}, original_price={$product->original_price}");
                $count++;
            }
        });

        // Also normalize any unscaled prices in temp_products table
        $tempCount = 0;
        \App\Models\TempProduct::chunk(100, function ($tempProducts) use (&$tempCount, $currencyService) {
            foreach ($tempProducts as $tp) {
                if (is_numeric($tp->price) && $tp->price > 150) {
                    $converted = $currencyService->convertToEur((float) $tp->price, 'EUR', $tp->city, $tp->city);
                    if ($converted !== null && $converted != $tp->price) {
                        $tp->update(['price' => $converted]);
                        $tempCount++;
                    }
                }
            }
        });

        \Illuminate\Support\Facades\Cache::flush();

        $this->info("Updated $count GlobalTix products, $tempCount temp products, and flushed cache.");
    }
}
