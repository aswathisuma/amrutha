<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Lead;
use App\Models\Service;
use App\Models\User;
use App\Services\CrmService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeadController extends Controller
{
    public function __construct(protected CrmService $crm) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $leads = Lead::when($user->isBranchScoped(), fn ($q) => $q->where(fn ($w) => $w->where('branch_id', $user->branch_id)->orWhereNull('branch_id')))
            ->latest()->get()->groupBy('stage');

        return view('leads.index', ['leads' => $leads, 'stages' => config('crm.lead_stages')]);
    }

    public function create()
    {
        return $this->panel('leads.create', [
            'services' => Service::orderBy('name')->get(),
            'branches' => Branch::orderBy('name')->get(),
        ], route('leads'));
    }

    public function store(Request $request)
    {
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

        $lead = $this->crm->createLead($data, $request->user());

        return $this->done($lead->customer_id ? 'Lead added · linked to existing customer '.$lead->customer->name : 'Lead added');
    }

    public function show(Request $request, Lead $lead)
    {
        $this->scope($request, $lead);

        return $this->panel('leads.show', [
            'l' => $lead->load(['customer', 'booking', 'owner', 'branch']),
            'stages' => config('crm.lead_stages'),
            'stageIdx' => array_search($lead->stage, config('crm.lead_stages'), true),
            'owners' => User::whereIn('role', ['admin', 'manager', 'staff'])->orderBy('name')->get(['id', 'name']),
        ], route('leads'));
    }

    public function stage(Request $request, Lead $lead)
    {
        $this->scope($request, $lead);
        $data = $request->validate([
            'stage' => ['required', Rule::in(config('crm.lead_stages'))],
            'lost_reason' => ['nullable', 'string', 'max:255'],
            'owner_user_id' => ['nullable', 'exists:users,id'],
        ]);
        $this->crm->updateLead($lead, $data, $request->user());

        return $this->done('Lead moved to '.$lead->stage, 'ok', route('leads.show', $lead));
    }

    public function convert(Request $request, Lead $lead)
    {
        $this->scope($request, $lead);
        $b = $this->crm->convertLead($lead, $request->user());

        return $this->done('Lead converted to booking '.$b->ref, 'ok', $request->user()->canSee('bookings') ? route('bookings.show', $b) : null);
    }

    protected function scope(Request $request, Lead $lead): void
    {
        $u = $request->user();
        abort_if($u->isBranchScoped() && $lead->branch_id && $lead->branch_id !== $u->branch_id, 403);
    }
}
