<?php

namespace App\Http\Requests\Admin\Odp;

use App\Models\Odp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOdpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50|unique:odps,code',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'pole_type' => ['required', Rule::in(array_keys(Odp::getPoleTypes()))],
            'capacity' => 'required|integer|min:1|max:255',
            'area_id' => 'nullable|exists:areas,id',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
