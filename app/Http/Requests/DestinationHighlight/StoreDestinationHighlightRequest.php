<?php

namespace App\Http\Requests\DestinationHighlight;

use Illuminate\Foundation\Http\FormRequest;

class StoreDestinationHighlightRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'             => ['required', 'string', 'max:255'],
            'destination_type' => ['required', 'string', 'in:city,country'],
            'destination_id'   => ['required', 'integer', 'exists:cities,id'],
            'product_ids'      => ['nullable', 'array'],
            'product_ids.*'    => ['integer', 'exists:products,id'],
            'product_api_ids'  => ['nullable', 'string', 'max:5000'],
            'image'            => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'description'      => ['nullable', 'string', 'max:1000'],
            'status'           => ['required', 'string', 'in:active,inactive'],
        ];
    }

    public function messages(): array
    {
        return [
            'image.max' => 'The image must not be larger than 5MB.',
        ];
    }
}
