<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_number' => $this->payment_number,
            'amount' => $this->amount,
            'payment_method' => $this->payment_method,
            'payment_channel' => $this->payment_channel,
            'reference_number' => $this->reference_number,
            'status' => $this->status,
            'created_at' => $this->created_at?->toDateTimeString(),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'invoice' => new InvoiceResource($this->whenLoaded('invoice')),
        ];
    }
}
