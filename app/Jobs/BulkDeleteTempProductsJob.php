<?php

namespace App\Jobs;

use App\Models\TempProduct;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BulkDeleteTempProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        public readonly array $ids
    ) {
        $this->onQueue('bulk');
    }

    public function handle(): void
    {
        TempProduct::query()
            ->whereIn('id', $this->ids)
            ->delete();
    }
}
