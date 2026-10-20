<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\BookingResource;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use App\Services\CrmService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeadController extends ApiController
{
    public function __construct(protected CrmService $crm) {}

    /** GET /leads?q=&stage=&source=&branch_id= */
    public function index(Request $request)
    {
        $this->requireSection($request, 'leads');
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'stage' => ['nullable', Rule::in(config('crm.lead_stages'))],
            'source' => ['nullable', Rule::in(config('crm.sources'))],
            'branch_id' => ['nullable', 'integer'],
        ]);
        $user = $request->user();

        $q = Lead::query()
            ->when($user->isBranchScoped(), fn ($q) => $q->where('branch_id', $user->branch_id))
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('name', 'like', "%$v%")->orWhere('phone', 'like', '%'.preg_replace('/\D/', '', $v).'%')))
            ->when($f['stage'] ?? null, fn ($q, $v) => $q->where('stage', $v))
            ->when($f['source'] ?? null, fn ($q, $v) => $q->where('source', $v))
            ->when($f['branch_id'] ?? null, fn ($q, $v) => $q->where('branch_id', $v));

        return $this->paginate($q, $request, LeadResource::class, ['id', 'created_at', 'value', 'stage']);
    }

    public function store(Request $request)
    {
        $this->requireSection($request, 'leads');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            'service_name' => ['required', 'string', 'exists:services,name'],
            'source' => ['required', Rule::in(config('crm.sources'))],
            'value' => ['nullable', 'integer', 'min:0'],
            'area' => ['nullable', 'string', 'max:120'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->created(new LeadResource($this->crm->createLead($data, $request->user())));
    }

    /** PATCH /leads/{id} — stage, owner, notes, lost reason. */
    public function update(Request $request, Lead $lead)
    {
        $this->requireSection($request, 'leads');
        $this->scope($request, $lead);
        $data = $request->validate([
            'stage' => ['sometimes', Rule::in(config('crm.lead_stages'))],
            'owner_user_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'lost_reason' => ['sometimes', 'nullable', 'string', 'max:255'],
            'value' => ['sometimes', 'integer', 'min:0'],
        ]);

        return $this->respond(new LeadResource($this->crm->updateLead($lead, $data, $request->user())));
    }

    public function convert(Request $request, Lead $lead)
    {
        $this->requireSection($request, 'leads');
        $this->scope($request, $lead);
        $data = $request->validate([
            'scheduled_on' => ['nullable', 'date'],
            'time_slot' => ['nullable', Rule::in(config('crm.time_slots'))],
        ]);

        return $this->created(new BookingResource($this->crm->convertLead($lead, $request->user(), $data)->load(['customer', 'history'])));
    }

    protected function scope(Request $request, Lead $lead): void
    {
        $u = $request->user();
        abort_if($u->isBranchScoped() && $lead->branch_id && $lead->branch_id !== $u->branch_id, 403, 'This lead belongs to another branch.');
    }
}
