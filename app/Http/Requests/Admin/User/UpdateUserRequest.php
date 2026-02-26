<?php

namespace App\Http\Requests\Admin\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($this->route('user'))],
            'phone' => 'nullable|string|max:20',
            'password' => ['nullable', Password::defaults()],
            'role' => 'required|in:admin,penagih,technician,finance',
            'area_id' => 'nullable|exists:areas,id',
            'is_active' => 'boolean',
        ];
    }
}
