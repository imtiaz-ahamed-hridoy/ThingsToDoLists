<?php

namespace App\Jobs;

use App\Http\Controllers\API\Webhook\GlobalTixWebhookController;
use App\Models\ProviderWebhookLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use ReflectionClass;

class ProcessGlobalTixBookingWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1200;
    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly int $webhookLogId,
        public readonly string $eventType,
        public readonly array $data
    ) {
    }

    public function handle(): void
    {
        $log = ProviderWebhookLog::find($this->webhookLogId);

        try {
            $controller = app(GlobalTixWebhookController::class);
            /** @var array<string, mixed> $summary */
            $summary = $controller->processEventData($this->eventType, $this->data);

            $status = $summary['failed'] > 0
                ? 'error'
                : (($summary['processed'] > 0 || $summary['updated'] > 0 || $summary['created'] > 0) ? 'processed' : 'skipped');

            if ($log) {
                $log->update([
                    'status' => $status,
                    'processed' => (int) ($summary['processed'] ?? 0),
                    'created' => (int) ($summary['created'] ?? 0),
                    'updated' => (int) ($summary['updated'] ?? 0),
                    'skipped' => (int) ($summary['skipped'] ?? 0),
                    'failed' => (int) ($summary['failed'] ?? 0),
                    'result' => $summary['result'] ?? [],
                ]);
            }
        } catch (\Throwable $e) {
            Log::channel('daily')->error('GlobalTix booking webhook background processing failed', [
                'event' => $this->eventType,
                'referenceNumber' => $this->data['referenceNumber'] ?? null,
                'message' => $e->getMessage(),
            ]);

            if ($log) {
                $log->update([
                    'status' => 'error',
                    'failed' => 1,
                    'error_message' => mb_substr((string) $e->getMessage(), 0, 65000),
                    'result' => [
                        'event' => $this->eventType,
                        'referenceNumber' => $this->data['referenceNumber'] ?? null,
                    ],
                ]);
            }

            throw $e;
        }
    }
}
