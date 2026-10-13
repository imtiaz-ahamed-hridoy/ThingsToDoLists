<?php

namespace App\Repositories\Contracts;

use App\Models\EmailTemplate;

interface EmailTemplateRepositoryInterface extends RepositoryInterface
{
    public function findByName(string $name): ?EmailTemplate;
}
