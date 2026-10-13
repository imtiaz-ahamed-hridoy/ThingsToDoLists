<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderWebhookLog extends Model
{
    protected $fillable = [
        'provider',
        'event',
        'status',
        'processed',
        'created',
        'updated',
        'skipped',
        'failed',
        'headers',
        'payload',
        'result',
        'error_message',
    ];

    protected $casts = [
        'headers' => 'array',
        'payload' => 'array',
        'result' => 'array',
    ];
}
