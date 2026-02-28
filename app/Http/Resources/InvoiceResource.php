<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'period_month' => $this->period_month,
            'period_year' => $this->period_year,
            'period_label' => $this->period_label,
            'package_name' => $this->package_name,
            'package_price' => $this->package_price,
            'additional_charges' => $this->additional_charges,
            'discount' => $this->discount,
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'remaining_amount' => $this->remaining_amount,
            'status' => $this->status,
            'due_date' => $this->due_date?->toDateString(),
            'paid_at' => $this->paid_at?->toDateTimeString(),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
        ];
    }
}
