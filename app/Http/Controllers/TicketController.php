<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use App\Services\CrmService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    public function __construct(protected CrmService $crm) {}

    public function index()
    {
        return view('support.index', [
            'tickets' => Ticket::with('customer')->latest()->latest('id')->paginate(25),
            'statuses' => config('crm.ticket_statuses'),
        ]);
    }

    public function create()
    {
        return $this->panel('support.create', [
            'customers' => Customer::orderBy('name')->get(),
            'bookings' => Booking::with('customer')->latest()->take(100)->get(),
            'staff' => User::whereIn('role', ['admin', 'manager', 'staff'])->orderBy('name')->pluck('name'),
        ], route('support'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'booking_id' => ['nullable', 'exists:bookings,id'],
            'issue' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(config('crm.ticket_priorities'))],
            'source' => ['nullable', Rule::in(['Phone', 'WhatsApp', 'Email', 'Walk-in', 'Website'])],
            'assigned_to' => ['nullable', 'string', 'max:120'],
        ]);
        $t = $this->crm->createTicket($data, $request->user());

        return $this->done('Ticket '.$t->number.' created', 'ok', route('support.show', $t));
    }

    public function show(Ticket $ticket)
    {
        return $this->panel('support.show', [
            't' => $ticket->load(['customer', 'booking', 'events.user']),
            'statuses' => config('crm.ticket_statuses'),
        ], route('support'));
    }

    public function update(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(config('crm.ticket_statuses'))],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->crm->changeTicketStatus($ticket, $data['status'], $request->user(), $data['note'] ?? null);

        return $this->done($ticket->number.' → '.$ticket->status, 'ok', $request->boolean('reopen') ? route('support.show', $ticket) : null);
    }

    public function comment(Request $request, Ticket $ticket)
    {
        $body = $request->validate(['body' => ['required', 'string', 'max:5000']])['body'];
        $this->crm->commentOnTicket($ticket, $body, $request->user());

        return $this->done('Comment added', 'ok', route('support.show', $ticket));
    }
}
