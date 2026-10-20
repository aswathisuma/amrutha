<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\AutomationResource;
use App\Models\AuditLog;
use App\Models\Automation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AutomationController extends ApiController
{
    public function index(Request $request)
    {
        $this->requireSection($request, 'automation');

        return $this->paginate(Automation::query(), $request, AutomationResource::class, ['id', 'runs'], 'id');
    }

    /** POST /automation-rules — status-triggered rule that sends an approved template. */
    public function store(Request $request)
    {
        $this->requireSection($request, 'automation');
        $data = $request->validate([
            'key' => ['required', 'alpha_dash', 'max:60', 'unique:automations,key'],
            'status_trigger' => ['required', Rule::in(config('crm.statuses')), 'unique:automations,status_trigger'],
            'template' => ['required', Rule::exists('wa_templates', 'name')->where('status', 'Approved')],
            'action' => ['nullable', 'string', 'max:120'],
            'enabled' => ['boolean'],
            'max_attempts' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $rule = Automation::create($data + [
            'trigger' => 'Booking Status = '.$data['status_trigger'],
            'action' => $data['action'] ?? 'Send WhatsApp template '.$data['template'],
            'enabled' => $data['enabled'] ?? true,
            'max_attempts' => $data['max_attempts'] ?? 3,
        ]);
        AuditLog::record('automation.created', $rule, null, $rule->only(['key', 'status_trigger', 'template', 'enabled']));

        return $this->created(new AutomationResource($rule));
    }

    /** PATCH /automation-rules/{id} — enable/disable or change template/attempts. */
    public function update(Request $request, Automation $automation)
    {
        $this->requireSection($request, 'automation');
        $data = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'template' => ['sometimes', Rule::exists('wa_templates', 'name')->where('status', 'Approved')],
            'max_attempts' => ['sometimes', 'integer', 'min:1', 'max:10'],
        ]);

        $before = $automation->only(array_keys($data));
        $automation->update($data);
        AuditLog::record('automation.updated', $automation, $before, $automation->only(array_keys($data)));

        return $this->respond(new AutomationResource($automation));
    }
}
