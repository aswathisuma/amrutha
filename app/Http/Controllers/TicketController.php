<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    public function index()
    {
        return view('support.index', [
            'tickets' => Ticket::with('customer')->latest()->get(),
            'statuses' => config('crm.ticket_statuses'),
        ]);
    }

    public function create()
    {
        return $this->panel('support.create', [
            'customers' => Customer::orderBy('name')->get(),
            'staff' => User::whereIn('role', ['admin', 'manager', 'staff'])->orderBy('name')->pluck('name'),
        ], route('support'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'issue' => ['required', 'string', 'max:255'],
            'priority' => ['required', Rule::in(config('crm.ticket_priorities'))],
            'assigned_to' => ['nullable', 'string', 'max:120'],
        ]);
        $t = Ticket::create($data + ['number' => Ticket::nextNumber(), 'status' => $data['assigned_to'] ? 'Assigned' : 'Open']);
        Activity::log($t->customer->name, 'complaint logged · '.$t->number);

        return $this->done('Ticket '.$t->number.' created');
    }

    public function update(Request $request, Ticket $ticket)
    {
        $ticket->update($request->validate(['status' => ['required', Rule::in(config('crm.ticket_statuses'))]]));

        return $this->done($ticket->number.' → '.$ticket->status);
    }
}
