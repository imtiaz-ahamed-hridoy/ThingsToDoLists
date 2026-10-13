<?php

namespace App\Repositories;

use App\Models\EmailTemplate;
use App\Repositories\Contracts\EmailTemplateRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

class EmailTemplateRepository extends BaseRepository implements EmailTemplateRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new EmailTemplate();
    }

    public function findByName(string $name): ?EmailTemplate
    {
        return $this->model->newQuery()->where('name', $name)->first();
    }
}
