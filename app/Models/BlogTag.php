<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogTag extends Model
{
    protected $fillable = ['name', 'slug', 'sort_order', 'status'];

    public function posts()
    {
        return $this->belongsToMany(BlogPost::class, 'blog_post_tag', 'tag_id', 'post_id')
            ->withPivot('position')
            ->orderByPivot('position');
    }
}
