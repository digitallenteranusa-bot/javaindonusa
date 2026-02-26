<?php

namespace App\Http\Requests\Admin\Olt;

use App\Models\Olt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOltRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'ip_address' => ['required', 'ip', Rule::unique('olts')->ignore($this->route('olt'))],
            'type' => ['required', Rule::in(array_keys(Olt::getTypes()))],
            'pon_ports' => ['required', Rule::in(array_keys(Olt::getPonPortOptions()))],
            'username' => 'nullable|string|max:100',
            'password' => 'nullable|string|max:100',
            'telnet_port' => 'required|integer|min:1|max:65535',
            'ssh_port' => 'required|integer|min:1|max:65535',
            'snmp_community' => 'nullable|string|max:100',
            'status' => ['required', Rule::in(array_keys(Olt::getStatuses()))],
            'notes' => 'nullable|string|max:1000',
            'firmware_version' => 'nullable|string|max:100',
        ];
    }
}
