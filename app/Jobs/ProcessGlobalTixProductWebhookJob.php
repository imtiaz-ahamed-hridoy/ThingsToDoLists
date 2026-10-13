<?php

namespace App\Jobs;

use App\Models\ProviderWebhookLog;
use App\Models\TempProduct;
use App\Services\GlobalTix\GlobalTixProductService;
use App\Services\GlobalTix\GlobalTixProductWebhookProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessGlobalTixProductWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1200;
    public int $tries = 3;
    public int $backoff = 30;
    public function __construct(
        public readonly int $webhookLogId,
        public readonly string $eventType,
        public readonly array $data
    ) {}

    public function handle(
        GlobalTixProductWebhookProcessor $processor,
        GlobalTixProductService $productService
    ): void {
        $log = ProviderWebhookLog::find($this->webhookLogId);
        $productApiId = trim((string) ($this->data['productId'] ?? ''));

        if ($productApiId === '') {
            $this->markLog($log, 'skipped', [
                'reason' => 'Missing productId in GlobalTix product webhook.',
            ], skipped: 1);
            return;
        }

        try {
            $summary = $processor->process($this->eventType, $this->data);

            if (! $this->shouldFetchMissingProductForReview($summary)) {
                $this->markLogFromSummary($log, $summary, [
                    'product_api_id' => $productApiId,
                    'event' => $this->eventType,
                ]);
                return;
            }

            if (strtoupper($this->eventType) === GlobalTixProductWebhookProcessor::EVENT_PRODUCT_INFO_UPDATE) {
                $infoResponse = $productService->getProductInfo($productApiId);
                $fullInfo = is_array($infoResponse['data'] ?? null) ? $infoResponse['data'] : [];

                if ($fullInfo === []) {
                    $this->markLog($log, 'skipped', [
                        'product_api_id' => $productApiId,
                        'reason' => 'GlobalTix product not in catalog and provider returned no product details.',
                    ], skipped: 1);
                    return;
                }

                $thumbnail = null;
                if (! empty($fullInfo['image'])) {
                    $thumbnail = $productService->getImage($fullInfo['image']);
                } elseif (! empty($fullInfo['media'][0]['name'])) {
                    $thumbnail = $fullInfo['media'][0]['name'];
                }

                TempProduct::updateOrCreate(
                    [
                        'product_api_id' => $productApiId,
                        'api_platform' => 'globaltix',
                    ],
                    [
                        'title' => $fullInfo['name'] ?? $fullInfo['title'] ?? $this->data['name'] ?? 'Untitled',
                        'description' => $fullInfo['description'] ?? null,
                        'price' => $fullInfo['fromPrice'] ?? $fullInfo['originalPrice'] ?? 0,
                        'city' => $fullInfo['city'] ?? $fullInfo['country'] ?? 'N/A',
                        'thumbnail' => $thumbnail,
                        'status' => 'pending',
                        'raw_data' => $fullInfo,
                        'last_import_status' => null,
                        'last_import_reason' => null,
                        'last_import_attempted_at' => null,
                    ]
                );

                $this->markLog($log, 'processed', [
                    'product_api_id' => $productApiId,
                    'result' => 'Queued missing GlobalTix product in temp review table.',
                ], processed: 1, created: 1);
                return;
            }

            $this->markLogFromSummary($log, $summary, [
                'product_api_id' => $productApiId,
                'event' => $this->eventType,
            ]);
        } catch (\Throwable $e) {
            Log::error('GlobalTix product webhook background processing failed', [
                'event' => $this->eventType,
                'product_api_id' => $productApiId,
                'message' => $e->getMessage(),
            ]);

            $this->markLog($log, 'error', [
                'product_api_id' => $productApiId,
                'reason' => $e->getMessage(),
            ], failed: 1, errorMessage: $e->getMessage());

            throw $e;
        }
    }

    private function shouldFetchMissingProductForReview(array $summary): bool
    {
        if (strtoupper($this->eventType) !== GlobalTixProductWebhookProcessor::EVENT_PRODUCT_INFO_UPDATE) {
            return false;
        }

        if (($summary['skipped'] ?? 0) <= 0 || ($summary['updated'] ?? 0) > 0 || ($summary['processed'] ?? 0) > 0) {
            return false;
        }

        return str_contains(
            (string) data_get($summary, 'result.reason', ''),
            'No matching product records updated'
        );
    }

    private function markLogFromSummary(?ProviderWebhookLog $log, array $summary, array $extraResult = []): void
    {
        $failed = (int) ($summary['failed'] ?? 0);
        $processed = (int) ($summary['processed'] ?? 0);
        $updated = (int) ($summary['updated'] ?? 0);
        $created = (int) ($summary['created'] ?? 0);
        $skipped = (int) ($summary['skipped'] ?? 0);
        $result = array_merge($extraResult, (array) ($summary['result'] ?? []));

        $status = match (true) {
            $failed > 0 => 'error',
            $processed > 0 || $updated > 0 || $created > 0 => 'processed',
            $skipped > 0 => 'skipped',
            default => 'processed',
        };

        $this->markLog(
            $log,
            $status,
            $result,
            processed: $processed,
            created: $created,
            updated: $updated,
            skipped: $skipped,
            failed: $failed,
            errorMessage: $failed > 0 ? (string) ($result['reason'] ?? 'GlobalTix product webhook processing failed.') : null
        );
    }

    private function markLog(
        ?ProviderWebhookLog $log,
        string $status,
        array $result,
        int $processed = 0,
        int $created = 0,
        int $updated = 0,
        int $skipped = 0,
        int $failed = 0,
        ?string $errorMessage = null
    ): void {
        if (! $log) {
            return;
        }

        $log->update([
            'status' => $status,
            'processed' => $processed,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => $failed,
            'result' => $result,
            'error_message' => $errorMessage ? mb_substr((string) $errorMessage, 0, 65000) : null,
        ]);
    }
}
