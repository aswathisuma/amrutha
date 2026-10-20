<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Technician;
use App\Services\BookingWorkflow;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public const FILTERS = ['All', 'New', 'Confirmed', 'Assigned', 'Service Started', 'Service Completed', 'Paid', 'Cancelled'];

    public function __construct(protected BookingWorkflow $workflow) {}

    public function index(Request $request)
    {
        $filter = in_array($request->query('status'), self::FILTERS, true) ? $request->query('status') : 'All';
        $base = $request->user()->scopeBookingQuery(Booking::query());
        $total = (clone $base)->count();

        $bookings = $base->with(['customer', 'technician'])
            ->when($filter !== 'All', fn ($q) => $q->where('status', $filter))
            ->latest()
            ->get();

        return view('bookings.index', ['bookings' => $bookings, 'filter' => $filter, 'filters' => self::FILTERS, 'total' => $total]);
    }

    public function create(Request $request)
    {
        $customers = Customer::orderBy('name')
            ->when($request->user()->isBranchScoped(), fn ($q) => $q->where('branch_id', $request->user()->branch_id))
            ->get();

        return $this->panel('bookings.create', [
            'customers' => $customers,
            'services' => Service::whereNotNull('base_price')->orderBy('name')->get(),
            'selected' => $request->integer('customer') ?: null,
        ], route('bookings'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'service_id' => ['required', 'exists:services,id'],
            'scheduled_on' => ['nullable', 'date'],
            'time_slot' => ['required', 'string'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $c = Customer::findOrFail($data['customer_id']);
        $s = Service::findOrFail($data['service_id']);

        $b = Booking::create([
            'customer_id' => $c->id,
            'service_id' => $s->id,
            'service_name' => $s->name,
            'area' => $c->area,
            'branch_id' => $c->branch_id,
            'scheduled_on' => $data['scheduled_on'] ?? today(),
            'time_slot' => $data['time_slot'],
            'description' => ($data['description'] ?? null) ?: '—',
            'status' => 'New',
            'est_amount' => $s->base_price ?? 0,
            'source' => 'Manual',
        ]);
        Activity::log($c->name, 'booking created');

        return $this->done('Booking '.$b->ref.' created', 'ok', route('bookings.show', $b));
    }

    public function show(Request $request, Booking $booking)
    {
        abort_unless($request->user()->canAccessBooking($booking), 403);
        $booking->load(['customer', 'technician', 'branch']);

        $statuses = config('crm.statuses');
        $idx = array_search($booking->status, $statuses, true);

        return $this->panel('bookings.show', [
            'b' => $booking,
            'statuses' => $statuses,
            'idx' => $idx === false ? -1 : $idx,
            'next' => $booking->nextStatus(),
        ], route('bookings'));
    }

    public function advance(Request $request, Booking $booking)
    {
        abort_unless($request->user()->canAccessBooking($booking), 403);
        $next = $this->workflow->advance($booking);
        if (! $next) {
            return $this->done('This booking cannot be advanced further', 'warn');
        }

        $reopen = $request->user()->canSee('bookings') ? route('bookings.show', $booking) : null;

        return $this->done('→ '.$next.' · WhatsApp auto-sent', 'wa', $reopen);
    }

    public function assignForm(Request $request, Booking $booking)
    {
        abort_unless($request->user()->canAccessBooking($booking), 403);

        return $this->panel('bookings.assign', [
            'b' => $booking,
            'techs' => $this->workflow->rankedTechnicians($booking)->take(5),
        ], route('bookings'));
    }

    public function assign(Request $request, Booking $booking)
    {
        abort_unless($request->user()->canAccessBooking($booking), 403);
        $tech = Technician::findOrFail($request->validate(['technician_id' => ['required', 'exists:technicians,id']])['technician_id']);
        $this->workflow->assign($booking, $tech);

        return $this->done('Technician assigned · WhatsApp sent', 'wa', route('bookings.show', $booking));
    }

    public function cancel(Request $request, Booking $booking)
    {
        abort_unless($request->user()->canAccessBooking($booking), 403);
        $this->workflow->cancel($booking);

        return $this->done('Booking cancelled', 'warn');
    }
}
