<?php

namespace Tests\Feature;

use App\Models\TempProduct;
use App\Services\Bokun\BokunService;
use App\Services\GlobalTix\GlobalTixProductService;
use App\Services\GlobalTix\GlobaltixService;
use App\Services\Import\TempProductImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TempProductBulkImportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        \Mockery::close();

        parent::tearDown();
    }

    public function test_bulk_import_uses_full_bokun_temp_raw_without_fetching_provider_again(): void
    {
        $fixture = $this->providerFixture('bokun_259906_raw.json');

        $provider = \Mockery::mock(BokunService::class);
        $provider->shouldReceive('getFullProductDataRaw')->never();
        $this->app->instance(BokunService::class, $provider);

        $tempProduct = $this->makeTempProduct([
            'product_api_id' => '259906',
            'api_platform' => 'bokun',
            'title' => 'Combo Tour',
            'raw_data' => $fixture,
        ]);

        $summary = app(TempProductImportService::class)->bulkImport([$tempProduct->id]);

        $this->assertTrue($summary['success']);
        $this->assertSame(1, $summary['imported']);
        $this->assertDatabaseMissing('temp_products', ['id' => $tempProduct->id]);
        $this->assertDatabaseHas('products', [
            'product_api_id' => '259906',
            'platform' => 'bokun',
        ]);
    }

    public function test_bulk_import_uses_full_globaltix_temp_raw_from_raw_analysis_document(): void
    {
        $fixture = $this->firstRawAnalysisGlobalTixPayload();

        $provider = \Mockery::mock(GlobaltixService::class);
        $provider->shouldReceive('getFullProductDataRaw')->never();
        $this->app->instance(GlobaltixService::class, $provider);

        $imageService = \Mockery::mock(GlobalTixProductService::class);
        $imageService->shouldReceive('getImage')
            ->zeroOrMoreTimes()
            ->andReturnUsing(fn (string $path) => 'https://img.fixture.test/' . ltrim($path, '/'));
        $this->app->instance(GlobalTixProductService::class, $imageService);

        $tempProduct = $this->makeTempProduct([
            'product_api_id' => (string) data_get($fixture, 'data.activity.data.id'),
            'api_platform' => 'globaltix',
            'title' => (string) data_get($fixture, 'data.activity.data.name', 'GlobalTix fixture'),
            'raw_data' => $fixture,
        ]);

        $summary = app(TempProductImportService::class)->bulkImport([$tempProduct->id]);

        $this->assertTrue($summary['success']);
        $this->assertSame(1, $summary['imported']);
        $this->assertDatabaseMissing('temp_products', ['id' => $tempProduct->id]);
        $this->assertDatabaseHas('products', [
            'product_api_id' => (string) data_get($fixture, 'data.activity.data.id'),
            'platform' => 'globaltix',
        ]);
    }

    public function test_bulk_import_fetches_full_provider_raw_when_temp_row_only_has_partial_payload(): void
    {
        $fixture = $this->providerFixture('bokun_259906_raw.json');

        $provider = \Mockery::mock(BokunService::class);
        $provider->shouldReceive('getFullProductDataRaw')
            ->once()
            ->with('259906')
            ->andReturn($fixture);
        $this->app->instance(BokunService::class, $provider);

        $tempProduct = $this->makeTempProduct([
            'product_api_id' => '259906',
            'api_platform' => 'bokun',
            'title' => 'Partial Bokun Row',
            'raw_data' => [
                'id' => '259906',
                'title' => 'Partial Bokun Row',
            ],
        ]);

        $summary = app(TempProductImportService::class)->bulkImport([$tempProduct->id]);

        $this->assertTrue($summary['success']);
        $this->assertSame(1, $summary['imported']);
        $this->assertDatabaseMissing('temp_products', ['id' => $tempProduct->id]);
        $this->assertDatabaseHas('products', [
            'product_api_id' => '259906',
            'platform' => 'bokun',
        ]);
    }

    public function test_bulk_import_skips_new_product_when_normalized_payload_has_no_bookable_availability(): void
    {
        $fixture = $this->providerFixture('bokun_259906_raw.json');
        $fixture['data']['availabilities'] = [];

        $tempProduct = $this->makeTempProduct([
            'product_api_id' => '259906',
            'api_platform' => 'bokun',
            'title' => 'Unavailable Bokun Row',
            'raw_data' => $fixture,
        ]);

        $summary = app(TempProductImportService::class)->bulkImport([$tempProduct->id]);

        $this->assertTrue($summary['success']);
        $this->assertSame(1, $summary['skipped']);
        $this->assertDatabaseMissing('products', [
            'product_api_id' => '259906',
            'platform' => 'bokun',
        ]);
        $this->assertDatabaseHas('temp_products', [
            'id' => $tempProduct->id,
            'last_import_status' => 'skipped',
        ]);
        $this->assertStringContainsString(
            'no valid availability',
            (string) TempProduct::findOrFail($tempProduct->id)->last_import_reason
        );
    }

    public function test_bulk_import_keeps_failed_rows_and_only_deletes_successful_temp_rows(): void
    {
        $successFixture = $this->providerFixture('bokun_259906_raw.json');

        $provider = \Mockery::mock(BokunService::class);
        $provider->shouldReceive('getFullProductDataRaw')
            ->once()
            ->with('bad-1')
            ->andReturn([
                'status' => false,
                'message' => 'Provider unavailable',
            ]);
        $this->app->instance(BokunService::class, $provider);

        $successfulTemp = $this->makeTempProduct([
            'product_api_id' => '259906',
            'api_platform' => 'bokun',
            'title' => 'Successful Bokun Row',
            'raw_data' => $successFixture,
        ]);

        $failedTemp = $this->makeTempProduct([
            'product_api_id' => 'bad-1',
            'api_platform' => 'bokun',
            'title' => 'Failed Bokun Row',
            'raw_data' => [
                'id' => 'bad-1',
            ],
        ]);

        $summary = app(TempProductImportService::class)->bulkImport([$successfulTemp->id, $failedTemp->id]);

        $this->assertTrue($summary['success']);
        $this->assertSame(1, $summary['imported']);
        $this->assertSame(1, $summary['failed']);
        $this->assertDatabaseMissing('temp_products', ['id' => $successfulTemp->id]);
        $this->assertDatabaseHas('temp_products', [
            'id' => $failedTemp->id,
            'last_import_status' => 'failed',
        ]);
        $this->assertDatabaseHas('products', [
            'product_api_id' => '259906',
            'platform' => 'bokun',
        ]);
    }

    private function makeTempProduct(array $attributes): TempProduct
    {
        return TempProduct::create(array_merge([
            'product_api_id' => 'fixture-id',
            'api_platform' => 'bokun',
            'title' => 'Fixture Temp Product',
            'description' => null,
            'slug' => null,
            'vendor_name' => null,
            'price' => 0,
            'rating' => 0,
            'review_count' => 0,
            'city' => 'Test City',
            'thumbnail' => null,
            'raw_data' => [],
            'status' => 'pending',
        ], $attributes));
    }

    private function providerFixture(string $fileName): array
    {
        $payload = json_decode((string) file_get_contents(base_path('tests/Fixtures/provider_raw/' . $fileName)), true);

        $this->assertIsArray($payload, 'Failed to load provider fixture: ' . $fileName);

        return $payload;
    }

    private function firstRawAnalysisGlobalTixPayload(): array
    {
        foreach ($this->rawAnalysisDocuments() as $index => $document) {
            if ($index === 0 || !is_array($document) || ($document['provider'] ?? null) !== 'globaltix') {
                continue;
            }

            $rawData = $document['raw_data'] ?? null;
            if (
                is_array($rawData)
                && isset($rawData['activity']['data'])
                && isset($rawData['activityOptions']['data'])
                && isset($rawData['availability']['data'])
            ) {
                return [
                    'status' => (bool) ($document['success'] ?? true),
                    'data' => $rawData,
                ];
            }
        }

        $this->fail('raw_analysis.json does not contain a complete GlobalTix raw payload.');
    }

    /**
     * @return array<int, array>
     */
    private function rawAnalysisDocuments(): array
    {
        $contents = (string) file_get_contents(base_path('raw_analysis.json'));
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
                        if (is_array($decoded)) {
                            $documents[] = $decoded;
                        }
                    }
                    $start = null;
                }
            }
        }

        $this->assertNotEmpty($documents, 'raw_analysis.json did not produce any JSON documents.');

        return $documents;
    }
}
