<?php

namespace App\Jobs;

use App\Services\Import\TempProductImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BulkImportTempProductsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;
    public int $tries = 3;
    public int $backoff = 20;

    public function __construct(
        public readonly array $ids
    ) {
        $this->onQueue('import');
    }

    public function uniqueId(): string
    {
        $sorted = $this->ids;
        sort($sorted);
        return 'bulk_import_' . md5(json_encode($sorted));
    }

    public function handle(TempProductImportService $importService): void
    {
        $summary = $importService->bulkImport($this->ids);
    }
}
