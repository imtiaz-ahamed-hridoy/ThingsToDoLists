<?php

namespace App\Http\Requests\FeatureType;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFeatureTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $typeId = $this->route('feature_type');
        if ($typeId instanceof \App\Models\FeatureType) {
            $typeId = $typeId->id;
        }

        return [
            'name' => 'required|string|max:255',
            'slug' => ['required', 'string', 'max:255', Rule::unique('feature_types', 'slug')->ignore($typeId)],
            'description' => 'nullable|string',
            'status' => 'nullable|boolean',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'integer|exists:products,id',
        ];
    }
}
