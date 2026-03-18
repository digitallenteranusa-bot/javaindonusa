<?php

namespace App\Http\Requests\Admin\Package;

use Illuminate\Foundation\Http\FormRequest;

class StorePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:20|unique:packages,code',
            'description' => 'nullable|string|max:500',
            'speed_download' => 'required|integer|min:128',
            'speed_upload' => 'required|integer|min:128',
            'price' => 'required|numeric|min:0',
            'setup_fee' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'mikrotik_profile' => 'nullable|string|max:50',
            'burst_limit' => 'nullable|string|max:50',
            'burst_threshold' => 'nullable|string|max:50',
            'burst_time' => 'nullable|string|max:20',
            'priority' => 'nullable|integer|min:1|max:8',
            'address_list' => 'nullable|string|max:50',
            'pppoe_pool' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }
}
