<?php

namespace App\Http\Requests\FeatureType;

use Illuminate\Foundation\Http\FormRequest;

class StoreFeatureTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:feature_types,slug',
            'description' => 'nullable|string',
            'status' => 'nullable|boolean',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'integer|exists:products,id',
        ];
    }
}
