<?php

namespace Tests\Feature;

use App\Models\AvailabilityCategoryPrice;
use App\Models\AvailabilityCheck;
use App\Models\OptionPricingCategory;
use App\Models\Price;
use App\Models\Product;
use App\Models\ProductOption;
use App\Services\Bokun\BokunService;
use App\Services\GlobalTix\GlobalTixProductService;
use App\Services\GlobalTix\GlobaltixService;
use App\Services\Import\ProductImportMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RawAnalysisFixtureCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        \Mockery::close();

        parent::tearDown();
    }

    public function test_raw_analysis_fixture_summary_matches_supported_catalog_schema(): void
    {
        $summary = $this->rawAnalysisSummaryDocument();

        $bokunEntries = array_filter($summary, fn ($value, $key) => is_string($key) && str_starts_with($key, 'bokun_'), ARRAY_FILTER_USE_BOTH);
        $globaltixEntries = array_filter($summary, fn ($value, $key) => is_string($key) && str_starts_with($key, 'globaltix_'), ARRAY_FILTER_USE_BOTH);

        $this->assertNotEmpty($bokunEntries, 'raw_analysis.json summary has no Bokun entries.');
        $this->assertNotEmpty($globaltixEntries, 'raw_analysis.json summary has no GlobalTix entries.');

        foreach ($bokunEntries as $entry) {
            $this->assertIsArray($entry);
            $this->assertContains('id', $entry['activity_keys'] ?? []);
            $this->assertContains('title', $entry['activity_keys'] ?? []);
            $this->assertContains('description', $entry['activity_keys'] ?? []);
            $this->assertContains('barcodeType', $entry['activity_keys'] ?? []);
            $this->assertContains('timeZone', $entry['activity_keys'] ?? []);
            $this->assertContains('bookingQuestions', $entry['activity_keys'] ?? []);
            $this->assertContains('passengerFields', $entry['activity_keys'] ?? []);
            $this->assertContains('photos', $entry['activity_keys'] ?? []);
            $this->assertContains('videos', $entry['activity_keys'] ?? []);
            $this->assertContains('googlePlace', $entry['activity_keys'] ?? []);
            $this->assertContains('pricingCategories', $entry['activity_keys'] ?? []);
            $this->assertContains('rates', $entry['activity_keys'] ?? []);
            $this->assertContains('startTimes', $entry['activity_keys'] ?? []);
            $this->assertContains('activityCategories', $entry['activity_keys'] ?? []);
            $this->assertContains('activityAttributes', $entry['activity_keys'] ?? []);
            $this->assertContains('defaultRateId', $entry['activity_keys'] ?? []);
            $this->assertContains('pickupService', $entry['activity_keys'] ?? []);
            $this->assertContains('dropoffService', $entry['activity_keys'] ?? []);
            $this->assertContains('difficultyLevel', $entry['activity_keys'] ?? []);
            $this->assertContains('pricesByDateRange', $entry['priceList_keys'] ?? []);

            if (($entry['avail_count'] ?? 0) > 0) {
                $this->assertContains('pricesByRate', $entry['firstAvail_keys'] ?? []);
                $this->assertContains('pricesByCategory', $entry['firstAvail_keys'] ?? []);
                $this->assertContains('soldOut', $entry['firstAvail_keys'] ?? []);
                $this->assertContains('unlimitedAvailability', $entry['firstAvail_keys'] ?? []);

                $bokunAvailabilitySample = json_decode((string) ($entry['firstAvail_sample'] ?? ''), true);
                $this->assertIsArray($bokunAvailabilitySample);
                $this->assertArrayHasKey('date', $bokunAvailabilitySample);
                $this->assertArrayHasKey('startTime', $bokunAvailabilitySample);
            }
        }

        foreach ($globaltixEntries as $entry) {
            $this->assertIsArray($entry);
            $this->assertContains('id', $entry['activity_keys'] ?? []);
            $this->assertContains('name', $entry['activity_keys'] ?? []);
            $this->assertContains('description', $entry['activity_keys'] ?? []);
            $this->assertContains('currency', $entry['activity_keys'] ?? []);
            $this->assertContains('merchant', $entry['activity_keys'] ?? []);
            $this->assertContains('image', $entry['activity_keys'] ?? []);
            $this->assertContains('media', $entry['activity_keys'] ?? []);
            $this->assertContains('whatToExpect', $entry['activity_keys'] ?? []);
            $this->assertContains('termsAndConditions', $entry['activity_keys'] ?? []);
            $this->assertContains('howToUseList', $entry['activity_keys'] ?? []);
            $this->assertContains('thingsToNote', $entry['activity_keys'] ?? []);
            $this->assertContains('inclusions', $entry['activity_keys'] ?? []);
            $this->assertContains('ticketType', $entry['option_keys'] ?? []);
            $this->assertContains('questions', $entry['option_keys'] ?? []);
            $this->assertContains('cancellationPolicy', $entry['option_keys'] ?? []);
            $this->assertContains('cancellationNotes', $entry['option_keys'] ?? []);
            $this->assertContains('publishStart', $entry['option_keys'] ?? []);
            $this->assertContains('publishEnd', $entry['option_keys'] ?? []);
            $this->assertContains('redeemStart', $entry['option_keys'] ?? []);
            $this->assertContains('redeemEnd', $entry['option_keys'] ?? []);
            $this->assertContains('ticketValidity', $entry['option_keys'] ?? []);
            $this->assertContains('ticketFormat', $entry['option_keys'] ?? []);
            $this->assertContains('definedDuration', $entry['option_keys'] ?? []);
            $this->assertContains('isDynamicPricing', $entry['option_keys'] ?? []);
            $this->assertContains('isCapacity', $entry['option_keys'] ?? []);
            $this->assertContains('timeSlot', $entry['option_keys'] ?? []);
            $this->assertContains('visitDate', $entry['option_keys'] ?? []);
            $this->assertContains('advanceBooking', $entry['option_keys'] ?? []);

            if (($entry['avail_count'] ?? 0) > 0) {
                $this->assertContains('ticket_type_id', $entry['firstAvail_keys'] ?? []);
                $this->assertContains('recommendedSellingPrice', $entry['firstAvail_keys'] ?? []);
                $this->assertContains('nettPrice', $entry['firstAvail_keys'] ?? []);
                $this->assertContains('seriesId', $entry['firstAvail_keys'] ?? []);
                $this->assertContains('seriesName', $entry['firstAvail_keys'] ?? []);
            }
        }

        $this->assertTrue(Schema::hasColumns('products', [
            'product_api_id',
            'provider',
            'platform',
            'title',
            'description',
            'thumbnail',
            'time_zone',
            'durationType',
            'durationText',
            'activityType',
            'bookingType',
            'scheduleType',
            'cutoffType',
            'capacityType',
            'meetingType',
            'defaultRateId',
            'pickupService',
            'dropoffService',
            'default_currency',
            'from_price',
            'original_price',
            'highlights',
            'inclusions',
            'exclusions',
            'know_before_you_go',
            'what_to_expect',
            'terms_and_conditions',
            'operating_hours',
            'things_to_note',
            'how_to_use',
            'activityCategories',
            'activityAttributes',
            'guidanceTypes',
            'startTimes',
            'startPoints',
            'bookableExtras',
            'merchant_name',
            'address_line',
            'postal_code',
            'raw_response',
            'raw_response_saved_at',
            'last_synced_at',
        ]));

        $this->assertTrue(Schema::hasColumns('product_options', [
            'product_id',
            'provider',
            'external_id',
            'name',
            'description',
            'currency',
            'ticket_format',
            'ticket_validity',
            'defined_duration',
            'is_dynamic_pricing',
            'is_capacity',
            'is_cancellable',
            'time_slot',
            'visit_date',
            'advance_booking',
            'questions',
            'cancellation_policy',
            'cancellation_notes',
            'publish_start',
            'publish_end',
            'redeem_start',
            'redeem_end',
        ]));

        $this->assertTrue(Schema::hasColumns('option_pricing_categories', [
            'product_option_id',
            'external_id',
            'category_code',
            'category_label',
            'age_from',
            'age_to',
            'occupancy',
            'group_size',
            'selling_price',
            'nett_price',
            'original_price',
            'recommended_price',
            'merchant_price',
            'currency',
            'min_purchase_qty',
            'max_purchase_qty',
        ]));

        $this->assertTrue(Schema::hasColumns('prices', [
            'product_id',
            'product_option_id',
            'pricing_category_id',
            'provider',
            'rate_id',
            'ticket_type_id',
            'category_code',
            'category_name',
            'valid_from',
            'valid_to',
            'selling_price',
            'nett_price',
            'original_price',
            'recommended_price',
            'merchant_price',
            'currency',
            'tax_amount',
            'tax_percentage',
            'discount_percentage',
            'discounted_price',
        ]));

        $this->assertTrue(Schema::hasColumns('availability', [
            'product_id',
            'product_option_id',
            'provider',
            'external_id',
            'available_date',
            'start_time',
            'end_time',
            'start_at',
            'time_zone',
            'capacity_total',
            'capacity_booked',
            'capacity_reserved',
            'unlimited_availability',
            'sold_out',
            'min_participants',
            'max_participants',
            'display_price',
            'currency',
            'series_id',
            'series_name',
            'raw_data',
        ]));

        $this->assertTrue(Schema::hasColumns('availability_category_prices', [
            'availability_id',
            'pricing_category_id',
            'category_external_id',
            'category_code',
            'category_label',
            'selling_price',
            'nett_price',
            'original_price',
            'recommended_price',
            'merchant_price',
            'tax_amount',
            'currency',
            'available_qty',
            'min_qty',
            'max_qty',
            'metadata',
        ]));
    }

    public function test_dedicated_bokun_raw_fixture_imports_and_drives_public_apis(): void
    {
        $payload = $this->providerFixture('bokun_259906_raw.json');

        /** @var ProductImportMapper $mapper */
        $mapper = app(ProductImportMapper::class);
        $product = $mapper->importBokunProduct($payload, true);

        $product = $this->freshProduct($product->id);

        $this->assertSame('bokun', $product->provider);
        $this->assertNotEmpty($product->title);
        $this->assertNotEmpty($product->options);
        $this->assertNotEmpty($product->prices);
        $this->assertNotEmpty($product->availabilities);
        $this->assertNotEmpty($product->productImages);
        $this->assertNotEmpty($product->locations);
        $this->assertNotEmpty($product->cancellationPolicies);
        $this->assertGreaterThanOrEqual(1, ProductOption::count());
        $this->assertGreaterThanOrEqual(1, OptionPricingCategory::count());
        $this->assertGreaterThanOrEqual(1, Price::count());
        $this->assertGreaterThanOrEqual(1, AvailabilityCheck::count());
        $this->assertGreaterThanOrEqual(1, AvailabilityCategoryPrice::count());

        $this->assertPublicCatalogEndpoints($product, 'bokun', 1);

        AvailabilityCheck::where('product_id', $product->id)->delete();

        $provider = \Mockery::mock(BokunService::class);
        $provider->shouldReceive('checkAvailability')
            ->once()
            ->with(
                \Mockery::type('string'),
                \Mockery::type('string'),
                \Mockery::type('string'),
                \Mockery::type('string')
            )
            ->andReturn([
                'availabilities' => array_values(array_filter(
                    $payload['data']['availabilities'] ?? [],
                    'is_array'
                )),
            ]);
        $this->app->instance(BokunService::class, $provider);

        $startDate = $this->startDateForBokun($payload);
        $availabilityResponse = $this->postJson('/api/v1/availability/check', [
            'product_id' => $product->id,
            'start_date' => $startDate,
            'end_date' => $startDate,
            'currency' => $product->default_currency,
        ]);

        $availabilityResponse->assertOk()
            ->assertJsonPath('message', 'Availability fetched and synced successfully.')
            ->assertJsonPath('data.product_id', $product->id);

        $dates = $availabilityResponse->json('data.dates');
        $this->assertNotEmpty($dates);
        $this->assertNotEmpty($dates[0]['time_slots']);
        $this->assertNotEmpty($dates[0]['time_slots'][0]['categories']);
    }

    public function test_dedicated_globaltix_raw_fixture_imports_and_drives_public_apis(): void
    {
        $payload = $this->providerFixture('globaltix_38997_raw.json');

        $imageService = \Mockery::mock(GlobalTixProductService::class);
        $imageService->shouldReceive('getImage')
            ->zeroOrMoreTimes()
            ->andReturnUsing(fn (string $path) => 'https://img.fixture.test/' . ltrim($path, '/'));
        $this->app->instance(GlobalTixProductService::class, $imageService);

        /** @var ProductImportMapper $mapper */
        $mapper = app(ProductImportMapper::class);
        $product = $mapper->importGlobalTixProduct($payload, true);

        $product = $this->freshProduct($product->id);

        $this->assertSame('globaltix', $product->provider);
        $this->assertNotEmpty($product->title);
        $this->assertNotEmpty($product->options);
        $this->assertNotEmpty($product->prices);
        $this->assertNotEmpty($product->availabilities);
        $this->assertNotEmpty($product->productImages);
        $this->assertNotEmpty($product->locations);
        $this->assertNotEmpty($product->cancellationPolicies);
        $this->assertGreaterThanOrEqual(1, ProductOption::count());
        $this->assertGreaterThanOrEqual(1, OptionPricingCategory::count());
        $this->assertGreaterThanOrEqual(1, Price::count());
        $this->assertGreaterThanOrEqual(1, AvailabilityCheck::count());
        $this->assertGreaterThanOrEqual(1, AvailabilityCategoryPrice::count());

        $this->assertPublicCatalogEndpoints($product, 'globaltix', 1);

        AvailabilityCheck::where('product_id', $product->id)->delete();

        $provider = \Mockery::mock(GlobaltixService::class);
        $provider->shouldReceive('checkAvailability')
            ->once()
            ->with(
                \Mockery::type('string'),
                \Mockery::type('string'),
                \Mockery::type('string'),
                \Mockery::type('string')
            )
            ->andReturn([
                'availabilities' => array_values(array_filter(
                    $payload['data']['availability']['data'] ?? [],
                    'is_array'
                )),
            ]);
        $this->app->instance(GlobaltixService::class, $provider);

        $startDate = $this->startDateForGlobalTix($payload);
        $availabilityResponse = $this->postJson('/api/v1/availability/check', [
            'product_id' => $product->id,
            'start_date' => $startDate,
            'end_date' => $startDate,
            'currency' => $product->default_currency,
        ]);

        $availabilityResponse->assertOk()
            ->assertJsonPath('message', 'Availability fetched and synced successfully.')
            ->assertJsonPath('data.product_id', $product->id);

        $dates = $availabilityResponse->json('data.dates');
        $this->assertNotEmpty($dates);
        $this->assertNotEmpty($dates[0]['time_slots']);
        $this->assertNotEmpty($dates[0]['time_slots'][0]['categories']);
    }

    public function test_raw_analysis_appended_globaltix_raw_documents_import_and_drive_public_apis(): void
    {
        $payloads = $this->rawAnalysisAppendedGlobaltixRawPayloads();

        $imageService = \Mockery::mock(GlobalTixProductService::class);
        $imageService->shouldReceive('getImage')
            ->zeroOrMoreTimes()
            ->andReturnUsing(fn (string $path) => 'https://img.fixture.test/' . ltrim($path, '/'));
        $this->app->instance(GlobalTixProductService::class, $imageService);

        /** @var ProductImportMapper $mapper */
        $mapper = app(ProductImportMapper::class);

        foreach ($payloads as $payload) {
            $mapper->importGlobalTixProduct($payload, true);
        }

        $products = Product::with([
            'options.pricingCategories',
            'prices',
            'availabilities.categoryPrices',
            'productImages',
            'locations',
            'cancellationPolicies',
        ])->orderBy('id')->get();

        $this->assertCount(count($payloads), $products);
        $this->assertTrue($products->every(fn (Product $product) => $product->provider === 'globaltix'));
        $this->assertTrue($products->every(fn (Product $product) => $product->options->isNotEmpty()));
        $this->assertTrue($products->every(fn (Product $product) => $product->prices->isNotEmpty()));
        $this->assertTrue($products->every(fn (Product $product) => $product->availabilities->isNotEmpty()));

        $target = $products->first();
        $targetPayload = $payloads[0];

        $this->assertPublicCatalogEndpoints($target, 'globaltix', count($payloads));

        AvailabilityCheck::where('product_id', $target->id)->delete();

        $provider = \Mockery::mock(GlobaltixService::class);
        $provider->shouldReceive('checkAvailability')
            ->once()
            ->with(
                \Mockery::type('string'),
                \Mockery::type('string'),
                \Mockery::type('string'),
                \Mockery::type('string')
            )
            ->andReturn([
                'availabilities' => array_values(array_filter(
                    $targetPayload['data']['availability']['data'] ?? [],
                    'is_array'
                )),
            ]);
        $this->app->instance(GlobaltixService::class, $provider);

        $startDate = $this->startDateForGlobalTix($targetPayload);
        $availabilityResponse = $this->postJson('/api/v1/availability/check', [
            'product_id' => $target->id,
            'start_date' => $startDate,
            'end_date' => $startDate,
            'currency' => $target->default_currency,
        ]);

        $availabilityResponse->assertOk()
            ->assertJsonPath('message', 'Availability fetched and synced successfully.')
            ->assertJsonPath('data.product_id', $target->id);
    }

    private function assertPublicCatalogEndpoints(Product $product, string $platform, int $expectedTotal): void
    {
        $listResponse = $this->getJson('/api/v1/product/list');

        $listResponse->assertOk()
            ->assertJsonPath('message', 'Data fetched successfully')
            ->assertJsonPath('pagination.total', $expectedTotal)
            ->assertJsonPath('data.0.product_api_id', $product->product_api_id);

        $showResponse = $this->postJson('/api/v1/product/show', [
            'product_api_id' => $product->product_api_id,
            'platform' => $platform,
        ]);

        $showResponse->assertOk()
            ->assertJsonPath('message', 'Data fetched successfully')
            ->assertJsonPath('data.product_api_id', $product->product_api_id);

        $showData = $showResponse->json('data');
        $this->assertNotEmpty($showData['options']);
        $this->assertNotEmpty($showData['options'][0]['pricing_categories']);
        $this->assertNotEmpty($showData['prices']);
        $this->assertNotEmpty($showData['media']['photos']);
    }

    private function freshProduct(int $productId): Product
    {
        return Product::with([
            'options.pricingCategories.tieredPrices',
            'prices',
            'availabilities.categoryPrices',
            'productImages',
            'locations',
            'cancellationPolicies',
        ])->findOrFail($productId);
    }

    private function providerFixture(string $fileName): array
    {
        $payload = json_decode((string) file_get_contents(base_path('tests/Fixtures/provider_raw/' . $fileName)), true);

        $this->assertIsArray($payload, 'Failed to load provider fixture: ' . $fileName);

        return $payload;
    }

    private function startDateForBokun(array $payload): string
    {
        foreach (($payload['data']['availabilities'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $date = $row['date'] ?? null;
            if (is_string($date) && $date !== '') {
                return date('Y-m-d', strtotime($date));
            }
        }

        $this->fail('The Bokun fixture has no usable availability date.');
    }

    private function startDateForGlobalTix(array $payload): string
    {
        foreach (($payload['data']['availability']['data'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $date = $row['date'] ?? null;
            if (is_string($date) && $date !== '') {
                return date('Y-m-d', strtotime($date));
            }
        }

        $this->fail('The GlobalTix fixture has no usable availability date.');
    }

    private function rawAnalysisSummaryDocument(): array
    {
        $documents = $this->rawAnalysisDocuments();
        $summary = $documents[0] ?? null;

        $this->assertIsArray($summary, 'raw_analysis.json did not yield a summary document.');

        return $summary;
    }

    /**
     * @return array<int, array>
     */
    private function rawAnalysisAppendedGlobaltixRawPayloads(): array
    {
        $payloads = [];

        foreach ($this->rawAnalysisDocuments() as $index => $document) {
            if ($index === 0 || !is_array($document)) {
                continue;
            }

            if (($document['provider'] ?? null) !== 'globaltix') {
                continue;
            }

            if (!isset($document['raw_data']) || !is_array($document['raw_data'])) {
                continue;
            }

            $rawData = $document['raw_data'];
            $hasActivity = isset($rawData['activity']['data']) && is_array($rawData['activity']['data']);
            $hasOptions = isset($rawData['activityOptions']['data']) && is_array($rawData['activityOptions']['data']);
            $hasAvailability = isset($rawData['availability']['data']) && is_array($rawData['availability']['data']) && $rawData['availability']['data'] !== [];

            if ($hasActivity && $hasOptions && $hasAvailability) {
                $payloads[] = [
                    'status' => (bool) ($document['success'] ?? true),
                    'data' => $rawData,
                ];
            }
        }

        $this->assertNotEmpty($payloads, 'raw_analysis.json does not contain appended GlobalTix raw documents that can be imported.');

        return $payloads;
    }

    /**
     * @return array<int, array>
     */
    private function rawAnalysisDocuments(): array
    {
        static $documents;

        if (is_array($documents)) {
            return $documents;
        }

        $contents = (string) file_get_contents(base_path('raw_analysis.json'));
        $documents = $this->decodeConcatenatedJsonDocuments($contents);

        $this->assertNotEmpty($documents, 'raw_analysis.json did not produce any JSON documents.');

        return $documents;
    }

    /**
     * @return array<int, array>
     */
    private function decodeConcatenatedJsonDocuments(string $contents): array
    {
        $documents = [];
        $length = strlen($contents);
        $depth = 0;
        $start = null;
        $inString = false;
        $escape = false;

        for ($index = 0; $index < $length; $index++) {
            $char = $contents[$index];

            if ($inString) {
                if ($escape) {
                    $escape = false;
                    continue;
                }

                if ($char === '\\') {
                    $escape = true;
                    continue;
                }

                if ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;
                continue;
            }

            if ($char === '{' || $char === '[') {
                if ($depth === 0) {
                    $start = $index;
                }
                $depth++;
                continue;
            }

            if ($char === '}' || $char === ']') {
                $depth--;

                if ($depth === 0 && $start !== null) {
                    $json = trim(substr($contents, $start, $index - $start + 1));
                    if ($json !== '') {
                        $decoded = json_decode($json, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
                        $this->assertIsArray($decoded, 'Failed to decode one of the concatenated JSON documents in raw_analysis.json.');
                        $documents[] = $decoded;
                    }
                    $start = null;
                }
            }
        }

        return $documents;
    }
}
