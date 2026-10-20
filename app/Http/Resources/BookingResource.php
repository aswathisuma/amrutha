<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Booking */
class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_ref' => $this->ref,
            'status' => $this->status,
            'status_reason' => $this->status_reason,
            'allowed_transitions' => $this->allowedTransitions(),
            'customer' => $this->whenLoaded('customer', fn () => ['id' => $this->customer->id, 'name' => $this->customer->name, 'phone' => $this->customer->phone]),
            'service' => ['id' => $this->service_id, 'name' => $this->service_name],
            'branch_id' => $this->branch_id,
            'technician' => $this->whenLoaded('technician', fn () => $this->technician ? ['id' => $this->technician->id, 'name' => $this->technician->name] : null),
            'area' => $this->area,
            'scheduled_on' => $this->scheduled_on?->toDateString(),
            'time_slot' => $this->time_slot,
            'description' => $this->description,
            'source' => $this->source,
            'charges' => [
                'estimate' => $this->est_amount,
                'service' => $this->final_amount,
                'materials' => (int) $this->material_charge,
                'discount' => (int) $this->discount,
                'subtotal' => $this->subtotal(),
                'approved_at' => $this->charges_approved_at?->toIso8601String(),
            ],
            'payment_status' => $this->payment_status,
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'history' => $this->whenLoaded('history', fn () => $this->history->map(fn ($h) => [
                'from' => $h->from_status,
                'to' => $h->to_status,
                'actor' => $h->actorName(),
                'reason' => $h->reason,
                'at' => $h->created_at?->toIso8601String(),
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
