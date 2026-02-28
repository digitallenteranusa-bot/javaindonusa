<?php

namespace App\Http\Requests\Api;

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
            'customer_id' => ['required', 'exists:customers,id'],
            'amount' => ['required', 'numeric', 'min:1000'],
            'payment_method' => ['required', 'in:cash,transfer,qris,ewallet'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Pelanggan harus dipilih.',
            'customer_id.exists' => 'Pelanggan tidak ditemukan.',
            'amount.required' => 'Jumlah pembayaran harus diisi.',
            'amount.min' => 'Jumlah pembayaran minimal Rp 1.000.',
            'payment_method.required' => 'Metode pembayaran harus dipilih.',
            'payment_method.in' => 'Metode pembayaran tidak valid.',
        ];
    }
}
