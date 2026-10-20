<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Branch;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $customers = Customer::with('bookings')
            ->when($user->isBranchScoped(), fn ($q) => $q->where('branch_id', $user->branch_id))
            ->orderBy('name')
            ->get();

        return view('customers.index', compact('customers'));
    }

    public function create()
    {
        return $this->panel('customers.create', ['branches' => Branch::orderBy('name')->get()], route('customers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email'],
            'area' => ['nullable', 'string', 'max:120'],
            'branch_id' => ['required', 'exists:branches,id'],
            'type' => ['required', Rule::in(['Residential', 'Commercial'])],
        ]);

        $c = Customer::create($data + ['tags' => ['New']]);
        Activity::log($c->name, 'customer added');

        return $this->done('Customer added');
    }

    public function show(Request $request, Customer $customer)
    {
        $user = $request->user();
        abort_if($user->isBranchScoped() && $customer->branch_id !== $user->branch_id, 403);

        $customer->load(['branch', 'bookings' => fn ($q) => $q->latest(), 'invoices', 'reviews', 'conversation.messages']);

        return $this->panel('customers.show', [
            'c' => $customer,
            'timeline' => $this->timeline($customer),
        ], route('customers'));
    }

    /** Merge the customer's touchpoints into one chronological feed. */
    protected function timeline(Customer $c)
    {
        $events = collect();
        if ($first = $c->conversation?->messages->first()) {
            $events->push(['at' => $first->sent_at, 'text' => 'contacted on WhatsApp']);
        }
        foreach ($c->bookings as $b) {
            $events->push(['at' => $b->created_at, 'text' => "booking {$b->ref} created · {$b->service_name}"]);
            if (in_array($b->status, ['Service Completed', 'Invoice Generated', 'Payment Pending', 'Paid', 'Closed'], true)) {
                $events->push(['at' => $b->scheduled_on, 'text' => "service completed · {$b->ref}"]);
            }
        }
        foreach ($c->invoices as $i) {
            $events->push(['at' => $i->issued_at ?? $i->created_at, 'text' => "invoice {$i->number} sent"]);
            if ($i->paid_at) {
                $events->push(['at' => $i->paid_at, 'text' => 'payment received · '.money($i->total)]);
            }
        }
        foreach ($c->reviews as $r) {
            $events->push(['at' => $r->created_at, 'text' => "feedback received · {$r->rating}★"]);
        }

        return $events->sortByDesc('at')->take(10)->values();
    }
}
