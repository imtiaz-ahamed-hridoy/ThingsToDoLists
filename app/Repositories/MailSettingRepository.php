<?php

namespace App\Repositories;

use App\Models\MailSetting;
use App\Repositories\Contracts\MailSettingRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

class MailSettingRepository extends BaseRepository implements MailSettingRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new MailSetting();
    }

    public function first(): ?MailSetting
    {
        return $this->model->newQuery()->first();
    }
}
