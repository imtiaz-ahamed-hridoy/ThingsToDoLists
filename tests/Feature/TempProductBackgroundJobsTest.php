<?php

namespace Tests\Feature;

use App\Jobs\BulkDeleteTempProductsJob;
use App\Jobs\BulkImportTempProductsJob;
use App\Jobs\ImportProviderProductIdsChunkJob;
use App\Jobs\ProcessGlobalTixProductWebhookJob;
use App\Jobs\QueueBokunActiveActivitiesImportJob;
use App\Jobs\QueueTempProductSearchJob;
use App\Jobs\SyncGlobalTixCatalogJob;
use App\Models\ProviderWebhookLog;
use App\Models\TempProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TempProductBackgroundJobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_temp_product_admin_actions_queue_background_jobs(): void
    {
        Queue::fake();

        $admin = $this->admin();
        $first = TempProduct::create([
            'product_api_id' => 'gt-1',
            'api_platform' => 'globaltix',
            'title' => 'GlobalTix One',
        ]);
        $second = TempProduct::create([
            'product_api_id' => 'gt-2',
            'api_platform' => 'globaltix',
            'title' => 'GlobalTix Two',
        ]);

        $this->actingAs($admin)
            ->postJson(route('tempProduct.bulkImport'), ['ids' => [$first->id, $second->id]])
            ->assertAccepted()
            ->assertJsonPath('queued', true);

        Queue::assertPushed(BulkImportTempProductsJob::class, 1);

        $this->actingAs($admin)
            ->deleteJson(route('tempProduct.bulkDelete'), ['ids' => [$first->id, $second->id]])
            ->assertAccepted()
            ->assertJsonPath('queued', true);

        Queue::assertPushed(BulkDeleteTempProductsJob::class, 1);

        $this->actingAs($admin)
            ->postJson(route('tempProduct.search'), [
                'api_platform' => 'bokun',
                'page' => 1,
                'size' => 10,
                'textSearch' => 'tour',
            ])
            ->assertAccepted()
            ->assertJsonPath('queued', true);

        Queue::assertPushed(QueueTempProductSearchJob::class, 1);

        $this->actingAs($admin)
            ->postJson(route('tempProduct.globaltix.syncCatalog'), [
                'startDate' => now()->subDays(2)->toDateString(),
                'endDate' => now()->toDateString(),
            ])
            ->assertAccepted()
            ->assertJsonPath('queued', true);

        Queue::assertPushed(SyncGlobalTixCatalogJob::class, 1);

        $this->actingAs($admin)
            ->postJson(route('tempProduct.importByApiId'), [
                'api_platform' => 'globaltix',
                'product_api_id' => 'gt-direct-1',
                'force_update' => true,
            ])
            ->assertAccepted()
            ->assertJsonPath('queued', true);

        Queue::assertPushed(ImportProviderProductIdsChunkJob::class, 1);

        $this->actingAs($admin)
            ->postJson(route('tempProduct.bokun.queueActive'))
            ->assertAccepted()
            ->assertJsonPath('queued', true);

        Queue::assertPushed(QueueBokunActiveActivitiesImportJob::class, 1);
    }

    public function test_globaltix_product_webhook_is_queued_for_background_processing(): void
    {
        Queue::fake();
        config([
            'services.globaltix.webhook_secret' => '',
            'services.globaltix.webhook_require_signature' => false,
        ]);

        $response = $this->postJson('/api/v1/webhook/globaltix/product-info-update', [
            'eventType' => 'PRODUCT_INFO_UPDATE',
            'data' => [
                'productId' => '123456',
                'name' => 'Updated GlobalTix Product',
            ],
        ]);

        $response->assertAccepted()
            ->assertJsonPath('success', true)
            ->assertJsonPath('event', 'PRODUCT_INFO_UPDATE');

        Queue::assertPushed(ProcessGlobalTixProductWebhookJob::class, 1);

        $this->assertSame('queued', ProviderWebhookLog::query()->latest('id')->first()?->status);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin User',
            'email' => uniqid('admin_', true) . '@example.test',
            'password' => 'password',
            'role' => 'Admin',
            'is_agree' => true,
        ]);
    }
}
