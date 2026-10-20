<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\CrmService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketController extends ApiController
{
    /** GET /support/tickets?status=&priority= */
    public function index(Request $request)
    {
        $this->requireSection($request, 'support');
        $f = $request->validate([
            'status' => ['nullable', Rule::in(config('crm.ticket_statuses'))],
            'priority' => ['nullable', Rule::in(config('crm.ticket_priorities'))],
        ]);

        $q = Ticket::query()
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['priority'] ?? null, fn ($q, $v) => $q->where('priority', $v));

        return $this->paginate($q, $request, TicketResource::class, ['id', 'created_at', 'priority'], '-created_at');
    }

    public function store(Request $request, CrmService $crm)
    {
        $this->requireSection($request, 'support');
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'booking_id' => ['nullable', 'exists:bookings,id'],
            'issue' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(config('crm.ticket_priorities'))],
            'source' => ['nullable', 'string', 'max:40'],
            'assigned_to' => ['nullable', 'string', 'max:120'],
        ]);

        return $this->created(new TicketResource($crm->createTicket($data, $request->user())));
    }
}
