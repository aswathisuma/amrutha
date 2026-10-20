<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Technician */
class TechnicianResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'skill' => $this->skill,
            'branch_id' => $this->branch_id,
            'status' => $this->status,
            'rating' => $this->rating,
            'jobs_done' => $this->jobs_done,
            'queue' => $this->load,
        ];
    }
}
