<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Conversation */
class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer' => $this->whenLoaded('customer', fn () => ['id' => $this->customer->id, 'name' => $this->customer->name, 'phone' => $this->customer->phone]),
            'channel' => 'whatsapp',
            'mode' => $this->mode,
            'assigned_user_id' => $this->assigned_user_id,
            'unread' => $this->unread,
            'status' => $this->status,
            'needs_attention' => (bool) $this->needs_attention,
            'session_window_open' => $this->withinSessionWindow(),
            'last_message' => $this->whenLoaded('latestMessage', fn () => $this->latestMessage ? new MessageResource($this->latestMessage) : null),
            'last_message_at' => $this->last_message_at?->toIso8601String(),
        ];
    }
}
