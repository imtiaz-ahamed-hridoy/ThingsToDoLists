<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => 'nullable|string|max:100',
            'email'    => [
                'nullable',
                'string',
                'email',
                Rule::unique('users', 'email')->ignore($this->route('id')),
            ],
            'phone'    => 'required|max:20',
            'password' => 'nullable|string',
            'dob'      => 'nullable|string|max:100',
            'avatar'   => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'role'     => 'required|in:User,Company,Admin,Support,supplier,ContentCreator',
        ];
    }
}
