<?php

namespace App\Http\Requests\City;

use Illuminate\Foundation\Http\FormRequest;

class StoreCityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'city_name'          => 'required|string|max:255',
            'city_short_code'    => 'nullable|string|max:50',
            'city_is_capital'    => 'nullable|boolean',
            'slug'               => 'required|string|max:255|unique:cities,slug',
            'country_name'       => 'nullable|string|max:255',
            'country_short_code' => 'nullable|string|max:50',
            'country_slug'       => 'nullable|string|max:255',
            'currency_code'      => 'nullable|string|max:50',
            'mobile_prefix'      => 'nullable|string|max:50',
            'title'              => 'nullable|string|max:255',
            'short_description'  => 'nullable|string|max:500',
            'thumbnail_image'    => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp',
            'gallery_image.*'    => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp',
            'item_status'        => 'required|in:0,1,2,3',
        ];
    }
}
