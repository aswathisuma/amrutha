<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Customer */
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone_e164' => $this->phone,
            'email' => $this->email,
            'customer_type' => $this->type,
            'area' => $this->area,
            'branch_id' => $this->branch_id,
            'tags' => $this->tags ?? [],
            'whatsapp_opt_out' => (bool) $this->whatsapp_opt_out,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
