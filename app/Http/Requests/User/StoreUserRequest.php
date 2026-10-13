<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|unique:users,email',
            'phone'    => 'nullable|max:20',
            'dob'      => 'nullable|date',
            'password' => 'required|string',
            'avatar'   => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'role'     => 'required|in:User,Company,Admin,Support,supplier,ContentCreator',
        ];
    }
}
