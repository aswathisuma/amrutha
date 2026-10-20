<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Lead */
class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone_e164' => $this->phone,
            'customer_id' => $this->customer_id,
            'booking_id' => $this->booking_id,
            'service' => $this->service_name,
            'source' => $this->source,
            'stage' => $this->stage,
            'estimated_value' => $this->value,
            'area' => $this->area,
            'branch_id' => $this->branch_id,
            'owner_user_id' => $this->owner_user_id,
            'lost_reason' => $this->lost_reason,
            'note' => $this->note,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
