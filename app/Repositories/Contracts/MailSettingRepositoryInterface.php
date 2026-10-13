<?php

namespace App\Repositories\Contracts;

use App\Models\MailSetting;

interface MailSettingRepositoryInterface extends RepositoryInterface
{
    public function first(): ?MailSetting;
}
