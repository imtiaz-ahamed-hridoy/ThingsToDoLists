<?php

namespace App\Http\Requests\HomePageSection;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHomePageSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'             => 'nullable|string|max:255',
            'short_description' => 'nullable|string',
            'image'             => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:5120',
            'product_ids'       => 'nullable|array',
            'product_ids.*'     => 'integer|exists:products,id',
            'status'            => 'nullable|boolean',
        ];
    }
}
