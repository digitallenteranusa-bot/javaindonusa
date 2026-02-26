<?php

namespace App\Http\Requests\Admin\Olt;

use App\Models\Olt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOltStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_keys(Olt::getStatuses()))],
        ];
    }
}
