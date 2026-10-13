<?php

namespace App\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class Faq extends Model
{
    use LogsActivity;
    protected $fillable = [
        "question",
        "answer",
        "status",
    ];

    protected $casts = [
        'created_at'=> 'date: d-m-Y',
        'updated_at'=> 'date: d-m-Y',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        ];

        public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['question', 'answer', 'status'])
            ->logOnlyDirty()
            ->useLogName('faq');
    }
}
