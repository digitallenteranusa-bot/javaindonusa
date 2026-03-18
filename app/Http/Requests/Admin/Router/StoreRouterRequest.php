<?php

namespace App\Http\Requests\Admin\Router;

use Illuminate\Foundation\Http\FormRequest;

class StoreRouterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'ip_address' => 'required|ip|unique:routers,ip_address',
            'api_port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:50',
            'password' => 'required|string|max:100',
            'radius_server_id' => 'nullable|exists:radius_servers,id',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
