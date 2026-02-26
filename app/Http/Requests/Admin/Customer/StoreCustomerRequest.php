<?php

namespace App\Http\Requests\Admin\Customer;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ip_address' => $this->ip_address ?: null,
            'email' => $this->email ?: null,
            'latitude' => $this->latitude ?: null,
            'longitude' => $this->longitude ?: null,
            'collector_id' => $this->collector_id ?: null,
            'odp_id' => $this->odp_id ?: null,
            'billing_start_date' => $this->billing_start_date ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'rt_rw' => 'nullable|string|max:20',
            'kelurahan' => 'nullable|string|max:100',
            'kecamatan' => 'nullable|string|max:100',
            'phone' => 'required|string|max:20',
            'phone_alt' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'nik' => 'nullable|string|max:20',
            'package_id' => 'required|exists:packages,id',
            'area_id' => 'required|exists:areas,id',
            'router_id' => 'required|exists:routers,id',
            'collector_id' => 'nullable|exists:users,id',
            'odp_id' => 'nullable|exists:odps,id',
            'connection_type' => 'required|in:pppoe,static,hotspot',
            'pppoe_username' => 'nullable|string|max:100|unique:customers,pppoe_username',
            'pppoe_password' => 'nullable|string|max:100',
            'ip_address' => 'nullable|ip',
            'mac_address' => 'nullable|string|max:20',
            'onu_serial' => 'nullable|string|max:50',
            'billing_type' => 'required|in:prepaid,postpaid',
            'billing_date' => 'nullable|integer|min:1|max:28',
            'billing_start_date' => 'nullable|date',
            'is_rapel' => 'boolean',
            'rapel_months' => 'nullable|integer|min:1|max:12',
            'discount_type' => 'required|in:none,nominal,percentage',
            'discount_value' => 'nullable|numeric|min:0',
            'discount_reason' => 'nullable|string|max:255',
            'is_taxed' => 'boolean',
            'notes' => 'nullable|string|max:1000',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ];
    }
}
