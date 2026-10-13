<?php

namespace App\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmailLog extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'template_id',
        'user_id',
        'list_id',
        'status',
        'sent_at',
        'subject',
        'body'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function template()
    {
        return $this->belongsTo(EmailTemplate::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['user_id', 'status'])
            ->logOnlyDirty()
            ->useLogName('email');
    }
}
