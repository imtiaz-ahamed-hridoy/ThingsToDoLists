<?php

namespace App\Http\Requests\DynamicPage;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDynamicPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page_title'   => 'required|string',
            'page_content' => 'required|string',
            'page_slug'    => 'required|string',
            'status'       => 'required|in:Active,Inactive',
        ];
    }
}
