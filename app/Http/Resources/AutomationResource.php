<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Automation */
class AutomationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'trigger' => $this->trigger,
            'status_trigger' => $this->status_trigger,
            'action' => $this->action,
            'template' => $this->template,
            'enabled' => $this->enabled,
            'max_attempts' => $this->max_attempts,
            'timezone' => config('crm.display_timezone'),
            'runs' => $this->runs,
            'last_run_at' => $this->last_run_at?->toIso8601String(),
            'last_run_status' => $this->last_run_status,
        ];
    }
}
