<?php

namespace App\Http\Requests\Collector\Customer;

use Illuminate\Foundation\Http\FormRequest;

class StoreCollectorCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:500',
            'kelurahan' => 'nullable|string|max:100',
            'package_id' => 'required|exists:packages,id',
            'area_id' => 'required|exists:areas,id',
            'router_id' => 'nullable|exists:routers,id',
            'odp_id' => 'nullable|exists:odps,id',
            'pppoe_username' => 'nullable|string|max:100',
            'ip_address' => 'nullable|string|max:45',
            'onu_serial' => 'nullable|string|max:100',
            'connection_type' => 'required|in:pppoe,static',
            'billing_date' => 'required|integer|min:1|max:28',
            'billing_start_date' => 'nullable|date',
            'total_debt' => 'nullable|numeric|min:0',
            'rapel_months' => 'nullable|integer|min:0|max:12',
            'discount_type' => 'nullable|in:none,nominal,percentage',
            'discount_value' => 'nullable|numeric|min:0',
            'discount_reason' => 'nullable|string|max:255',
            'is_taxed' => 'boolean',
            'notes' => 'nullable|string|max:1000',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ];
    }
}
