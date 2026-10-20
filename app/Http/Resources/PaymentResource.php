<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Payment */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'type' => $this->type,
            'amount' => $this->amount,
            'method' => $this->method,
            'reference' => $this->reference,
            'status' => $this->status,
            'note' => $this->note,
            'received_at' => $this->received_at?->toIso8601String(),
        ];
    }
}
