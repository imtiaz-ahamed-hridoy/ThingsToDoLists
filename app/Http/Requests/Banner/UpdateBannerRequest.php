<?php

namespace App\Http\Requests\Banner;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'nullable|string|max:255',
            'video_url' => 'nullable|url',
            'video_file' => 'nullable|file|mimes:mp4,webm,mov,ogg,avi,mkv,flv,wmv,m4v,3gp,qt|max:204800',
            'poster_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg,bmp,tiff|max:10240',
            'status' => 'nullable|boolean',
            'clear_video' => 'nullable|boolean',
            'clear_poster' => 'nullable|boolean',
        ];
    }
}
