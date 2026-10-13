<?php

namespace App\Http\Requests\Blog;

use Illuminate\Foundation\Http\FormRequest;

class StoreBlogPostRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'title'              => 'required|string|max:255',
            'slug'               => 'nullable|string|max:255|unique:blog_posts,slug',
            'subtitle'           => 'nullable|string|max:255',
            'excerpt'            => 'nullable|string',
            'content'            => 'nullable|string',
            'featured_image'     => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:10240',
            'category_id'        => 'nullable|exists:blog_categories,id',
            'category_new'       => 'nullable|string|max:255',
            'published'          => 'nullable|boolean',
            'published_at'       => 'nullable|date',
            'is_featured'        => 'nullable|boolean',
            'minutes_to_read'    => 'nullable|integer|min:1|max:999',
            'tags'               => 'nullable|array',
            'tags.*'             => 'nullable',
            'tags_new'           => 'nullable|string',
            'child_title'        => 'nullable|array',
            'child_title.*'      => 'nullable|string|max:255',
            'child_subtitle'     => 'nullable|array',
            'child_subtitle.*'   => 'nullable|string|max:255',
            'child_description'  => 'nullable|array',
            'child_description.*' => 'nullable|string',
            'child_image'        => 'nullable|array',
            'child_image.*'      => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:10240',
        ];
    }
}
