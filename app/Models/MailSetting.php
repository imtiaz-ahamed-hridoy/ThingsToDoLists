<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MailSetting extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
      'mailer',
      'host',
      'port',
      'username',
      'form_address',
      'password',
      'encryption',
    ];

}
