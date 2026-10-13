<?php

namespace App\Repositories\Contracts;

use App\Models\ProviderWebhookLog;
use Illuminate\Database\Eloquent\Collection;

interface ProviderWebhookLogRepositoryInterface extends RepositoryInterface
{
    public function getByProvider(string $provider): Collection;

    public function getByStatus(string $status): Collection;

    public function getRecent(int $limit = 20): Collection;
}
