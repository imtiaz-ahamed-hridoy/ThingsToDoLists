<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    protected $table= "activity_log";
    protected $guarded = [];

    public function causer()
    {
        return $this->morphTo(); // points to any model (usually User)
    }
}
