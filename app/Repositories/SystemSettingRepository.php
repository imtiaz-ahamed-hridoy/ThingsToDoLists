<?php

namespace App\Repositories;

use App\Models\SystemSetting;
use App\Repositories\Contracts\SystemSettingRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

class SystemSettingRepository extends BaseRepository implements SystemSettingRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new SystemSetting();
    }

    public function first(): ?SystemSetting
    {
        return $this->model->newQuery()->first();
    }
}
