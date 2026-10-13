<?php

namespace App\Http\Requests\Testimonial;

use Illuminate\Foundation\Http\FormRequest;

class StoreTestimonialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'country' => 'nullable|string|max:255',
            'country_icon' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp',
            'country_icon_manual' => 'nullable|string|max:255',
            'avater' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp',
            'description' => 'nullable|string',
            'footer_description' => 'nullable|string',
            'rating' => 'required|integer|min:1|max:5',
            'status' => 'required|boolean',
        ];
    }
}
