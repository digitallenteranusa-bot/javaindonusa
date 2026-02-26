<?php

namespace App\Http\Requests\Admin\Area;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'code' => ['required', 'string', 'max:20', Rule::unique('areas')->ignore($this->route('area'))->whereNull('deleted_at')],
            'description' => 'nullable|string|max:500',
            'parent_id' => 'nullable|exists:areas,id',
            'collector_id' => 'nullable|exists:users,id',
            'is_active' => 'boolean',
            'coverage_radius' => 'nullable|numeric|min:0',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ];
    }
}
