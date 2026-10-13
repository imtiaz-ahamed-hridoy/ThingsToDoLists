<?php

namespace App\Http\Requests\HomePageSection;

use Illuminate\Foundation\Http\FormRequest;

class ReorderHomePageSectionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'orders'           => 'required|array',
            'orders.*.id'       => 'required|integer|exists:home_page_sections,id',
            'orders.*.sort_order' => 'required|integer|min:0',
        ];
    }
}
