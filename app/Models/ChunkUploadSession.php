<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChunkUploadSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'temp_id',
        'file_name',
        'disk',
        'folder',
        'total_chunks',
        'uploaded_chunks',
        'is_completed',
        'final_path',
    ];

}
