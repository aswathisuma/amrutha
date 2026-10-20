<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeadController extends Controller
{
    public function index()
    {
        $leads = Lead::latest()->get()->groupBy('stage');

        return view('leads.index', ['leads' => $leads, 'stages' => config('crm.lead_stages')]);
    }

    public function create()
    {
        return $this->panel('leads.create', [
            'services' => Service::orderBy('name')->get(),
        ], route('leads'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            'service_name' => ['required', 'string'],
            'source' => ['required', Rule::in(config('crm.sources'))],
            'value' => ['nullable', 'integer', 'min:0'],
            'area' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $lead = Lead::create($data + ['stage' => 'New Lead', 'value' => $data['value'] ?? 0]);
        Activity::log($lead->name, 'lead created');

        return $this->done('Lead added');
    }

    public function show(Lead $lead)
    {
        return $this->panel('leads.show', [
            'l' => $lead,
            'stages' => config('crm.lead_stages'),
            'stageIdx' => array_search($lead->stage, config('crm.lead_stages'), true),
        ], route('leads'));
    }

    public function stage(Request $request, Lead $lead)
    {
        $lead->update($request->validate(['stage' => ['required', Rule::in(config('crm.lead_stages'))]]));

        return $this->done('Lead moved to '.$lead->stage, 'ok', route('leads.show', $lead));
    }

    /** Turn a lead into a customer (matched by phone) and a new booking. */
    public function convert(Lead $lead)
    {
        if ($lead->booking_id) {
            $lead->update(['stage' => 'Converted']);

            return $this->done('Lead already has booking '.$lead->booking->ref, 'warn');
        }

        $customer = ($lead->phone ? Customer::where('phone', $lead->phone)->first() : null)
            ?? Customer::create([
                'name' => $lead->name,
                'phone' => $lead->phone ?: '+91',
                'area' => $lead->area,
                'branch_id' => 1,
                'tags' => ['New'],
            ]);

        $svc = Service::where('name', $lead->service_name)->first();
        $booking = Booking::create([
            'customer_id' => $customer->id,
            'service_id' => $svc?->id,
            'service_name' => $lead->service_name ?? 'General Maintenance',
            'area' => $customer->area,
            'branch_id' => $customer->branch_id,
            'scheduled_on' => today()->addDay(),
            'time_slot' => '11:00 AM',
            'description' => $lead->note,
            'status' => 'New',
            'est_amount' => $lead->value ?: ($svc?->base_price ?? 0),
            'source' => $lead->source,
        ]);

        $lead->update(['stage' => 'Converted', 'booking_id' => $booking->id]);
        Activity::log($customer->name, 'lead converted to booking '.$booking->ref);

        return $this->done('Lead converted to booking '.$booking->ref);
    }
}
