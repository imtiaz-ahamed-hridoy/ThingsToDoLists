<?php

namespace App\Repositories\Contracts;

use App\Models\SystemSetting;

interface SystemSettingRepositoryInterface extends RepositoryInterface
{
    public function first(): ?SystemSetting;
}
