<?php

namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:categories,slug',
            'type' => 'nullable|string|max:255',
            'parent_category_id' => 'nullable|exists:categories,id',
            'city_id' => 'nullable|exists:cities,id',
            'location_country' => 'nullable|string|max:255',
            'status' => 'required|in:0,1',
            'show_in_search' => 'nullable|boolean',
            'search_label' => 'nullable|string|max:255',
            'search_subtitle' => 'nullable|string|max:255',
            'search_icon' => 'nullable|string|max:50',
            'search_sort_order' => 'nullable|integer|min:0|max:999999',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'integer|distinct|exists:products,id',
            'product_api_ids' => 'nullable|string|max:10000',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('slug')) {
            $this->merge(['slug' => Str::slug((string) $this->input('slug'))]);
        }
    }
}
