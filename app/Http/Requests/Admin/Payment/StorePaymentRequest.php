<?php

namespace App\Http\Requests\Admin\Payment;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'required|exists:customers,id',
            'amount' => 'required|numeric|min:1000',
            'payment_method' => 'required|in:cash,transfer',
            'transfer_proof' => 'nullable|string',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
