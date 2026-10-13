<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\CancellationPolicy;

class CancellationPolicyDetail extends Model
{
    protected $fillable = [
        'cancellation_policy_id',
        'cutoffHours',
        'charge',
        'chargeType',
        'percentage',
        'description',
    ];

    public function policy()
    {
        return $this->belongsTo(CancellationPolicy::class, 'cancellation_policy_id');
    }
}
