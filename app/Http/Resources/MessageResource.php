<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Message */
class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'direction' => $this->direction,
            'body' => $this->body,
            'is_template' => $this->is_template,
            'template_name' => $this->template_name,
            'status' => $this->status,
            'error_code' => $this->error_code,
            'sent_by' => $this->sent_by,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'status_at' => $this->status_at?->toIso8601String(),
        ];
    }
}
