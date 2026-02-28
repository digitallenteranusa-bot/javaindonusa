<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'name' => $this->name,
            'address' => $this->address,
            'kelurahan' => $this->kelurahan,
            'kecamatan' => $this->kecamatan,
            'phone' => $this->phone,
            'email' => $this->email,
            'status' => $this->status,
            'total_debt' => $this->total_debt,
            'join_date' => $this->join_date?->toDateString(),
            'package' => new PackageResource($this->whenLoaded('package')),
            'area' => new AreaResource($this->whenLoaded('area')),
        ];
    }
}
