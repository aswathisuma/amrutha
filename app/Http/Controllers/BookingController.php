<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Technician;
use App\Services\BookingWorkflow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            ->latest()->latest('id')
            ->paginate(25)->withQueryString();

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
            'time_slot' => ['required', Rule::in(config('crm.time_slots'))],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $b = $this->workflow->create($data, $request->user());

        return $this->done('Booking '.$b->ref.' created', 'ok', route('bookings.show', $b));
    }

    public function show(Request $request, Booking $booking)
    {
        $this->authorizeBooking($request, $booking);
        $booking->load(['customer', 'technician', 'branch', 'invoice', 'history.actor', 'attachments', 'assignedBy']);

        $statuses = config('crm.statuses');
        $idx = array_search($booking->status, $statuses, true);
        // Exception statuses keep their place on the happy path at the last normal step reached.
        if ($idx === false) {
            $lastNormal = $booking->history->reverse()->first(fn ($h) => in_array($h->from_status, $statuses, true));
            $idx = $lastNormal ? array_search($lastNormal->from_status, $statuses, true) : -1;
        }

        return $this->panel('bookings.show', [
            'b' => $booking,
            'statuses' => $statuses,
            'idx' => $idx,
            'next' => $booking->nextStatus(),
            'canApprove' => $this->workflow->canApprove($request->user()),
        ], route('bookings'));
    }

    public function advance(Request $request, Booking $booking)
    {
        $this->authorizeBooking($request, $booking);
        $b = $this->workflow->advance($booking, $request->user());

        return $this->done('→ '.$b->status.' · customer notified', 'wa', $this->reopenFor($request, $b));
    }

    /** Modal for exception outcomes: reschedule, cancel, no-show (reason required). */
    public function statusForm(Request $request, Booking $booking)
    {
        $this->authorizeBooking($request, $booking);
        $action = $request->query('action');
        abort_unless(in_array($action, ['Rescheduled', 'Cancelled', 'No Show'], true) && in_array($action, $booking->allowedTransitions(), true), 404);

        return $this->panel('bookings.status', ['b' => $booking, 'action' => $action], route('bookings'));
    }

    public function changeStatus(Request $request, Booking $booking)
    {
        $this->authorizeBooking($request, $booking);
        $data = $request->validate([
            'status' => ['required', Rule::in(config('crm.exception_statuses'))],
            'reason' => ['required', 'string', 'max:255'],
            'scheduled_on' => ['required_if:status,Rescheduled', 'nullable', 'date'],
            'time_slot' => ['required_if:status,Rescheduled', 'nullable', Rule::in(config('crm.time_slots'))],
        ]);

        $b = match ($data['status']) {
            'Rescheduled' => $this->workflow->reschedule($booking, $data['scheduled_on'], $data['time_slot'], $data['reason'], $request->user()),
            'No Show' => $this->workflow->markNoShow($booking, $data['reason'], $request->user()),
            default => $this->workflow->cancel($booking, $data['reason'], $request->user()),
        };

        return $this->done('Booking '.$b->ref.' → '.$b->status, 'warn', $this->reopenFor($request, $b));
    }

    public function assignForm(Request $request, Booking $booking)
    {
        $this->authorizeBooking($request, $booking);

        return $this->panel('bookings.assign', [
            'b' => $booking,
            'techs' => $this->workflow->rankedTechnicians($booking)->take(6),
        ], route('bookings'));
    }

    public function assign(Request $request, Booking $booking)
    {
        $this->authorizeBooking($request, $booking);
        $tech = Technician::findOrFail($request->validate(['technician_id' => ['required', 'exists:technicians,id']])['technician_id']);
        $this->workflow->assign($booking, $tech, $request->user());

        return $this->done($tech->name.' assigned · customer notified', 'wa', route('bookings.show', $booking));
    }

    // ---------------------------------------------------------- final charges

    public function chargesForm(Request $request, Booking $booking)
    {
        $this->authorizeBooking($request, $booking);
        abort_unless($booking->status === 'Service Completed', 404);

        return $this->panel('bookings.charges', [
            'b' => $booking,
            'threshold' => config('crm.charge_approval_threshold'),
            'canApprove' => $this->workflow->canApprove($request->user()),
        ], route('bookings'));
    }

    public function saveCharges(Request $request, Booking $booking)
    {
        $this->authorizeBooking($request, $booking);
        $data = $request->validate([
            'final_amount' => ['required', 'integer', 'min:0', 'max:10000000'],
            'material_charge' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'discount' => ['nullable', 'integer', 'min:0', 'max:10000000'],
        ]);

        $result = $this->workflow->saveCharges($booking, $data['final_amount'], $data['material_charge'] ?? 0, $data['discount'] ?? 0, $request->user());

        return $result === 'invoiced'
            ? $this->done('Charges approved · invoice '.$booking->fresh()->invoice->number.' generated', 'wa', $this->reopenFor($request, $booking))
            : $this->done('Charges saved — waiting for manager approval', 'warn', $this->reopenFor($request, $booking));
    }

    public function approveCharges(Request $request, Booking $booking)
    {
        $this->authorizeBooking($request, $booking);
        $inv = $this->workflow->approveCharges($booking, $request->user());

        return $this->done('Charges approved · invoice '.$inv->number.' generated', 'wa', route('bookings.show', $booking));
    }

    // ------------------------------------------------- work notes & photos

    public function workForm(Request $request, Booking $booking)
    {
        $this->authorizeBooking($request, $booking);

        return $this->panel('bookings.work', ['b' => $booking->load('attachments')], route($request->user()->homeSection()));
    }

    /** Technician notes + before/after photos, stored on the private disk (spec §4.2 step 13). */
    public function saveWork(Request $request, Booking $booking)
    {
        $this->authorizeBooking($request, $booking);
        $data = $request->validate([
            'work_notes' => ['nullable', 'string', 'max:5000'],
            'label' => ['nullable', Rule::in(['before', 'after', 'receipt', 'other'])],
            'photos' => ['nullable', 'array', 'max:6'],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp,heic,pdf', 'max:8192'],
        ]);

        if (array_key_exists('work_notes', $data)) {
            $booking->update(['work_notes' => $data['work_notes']]);
        }
        foreach ($request->file('photos', []) as $file) {
            $booking->attachments()->create([
                'disk' => 'local',
                'path' => $file->store("attachments/bookings/{$booking->id}", 'local'),
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 200),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
                'label' => $data['label'] ?? 'other',
                'uploaded_by' => $request->user()->id,
            ]);
        }
        AuditLog::record('booking.work_updated', $booking, null, ['photos' => count($request->file('photos', [])), 'notes' => filled($data['work_notes'] ?? null)]);

        return $this->done('Work details saved', 'ok');
    }

    protected function authorizeBooking(Request $request, Booking $booking): void
    {
        abort_unless($request->user()->canAccessBooking($booking), 403);
    }

    protected function reopenFor(Request $request, Booking $b): ?string
    {
        return $request->user()->canSee('bookings') ? route('bookings.show', $b) : null;
    }
}
