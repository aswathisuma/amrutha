<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\WorkflowException;
use App\Http\Resources\BookingResource;
use App\Http\Resources\InvoiceResource;
use App\Models\Booking;
use App\Models\Technician;
use App\Services\BookingWorkflow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookingController extends ApiController
{
    public function __construct(protected BookingWorkflow $workflow) {}

    /** GET /bookings?status=&date_from=&date_to=&branch_id=&technician_id=&customer_id= */
    public function index(Request $request)
    {
        $this->requireSection($request, 'bookings', 'field');
        $f = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(config('crm.transitions')))],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'branch_id' => ['nullable', 'integer'],
            'technician_id' => ['nullable', 'integer'],
            'customer_id' => ['nullable', 'integer'],
        ]);

        $q = $request->user()->scopeBookingQuery(Booking::query())->with(['customer', 'technician'])
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['date_from'] ?? null, fn ($q, $v) => $q->whereDate('scheduled_on', '>=', $v))
            ->when($f['date_to'] ?? null, fn ($q, $v) => $q->whereDate('scheduled_on', '<=', $v))
            ->when($f['branch_id'] ?? null, fn ($q, $v) => $q->where('branch_id', $v))
            ->when($f['technician_id'] ?? null, fn ($q, $v) => $q->where('technician_id', $v))
            ->when($f['customer_id'] ?? null, fn ($q, $v) => $q->where('customer_id', $v));

        return $this->paginate($q, $request, BookingResource::class, ['id', 'created_at', 'scheduled_on', 'status'], '-created_at');
    }

    public function store(Request $request)
    {
        $this->requireSection($request, 'bookings');
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'service_id' => ['required', 'exists:services,id'],
            'scheduled_on' => ['required', 'date'],
            'time_slot' => ['required', Rule::in(config('crm.time_slots'))],
            'description' => ['nullable', 'string', 'max:1000'],
            'area' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', Rule::in([...config('crm.sources'), 'Manual'])],
        ]);

        $b = $this->workflow->create($data, $request->user(), 'API');

        return $this->created(new BookingResource($b->load(['customer', 'technician', 'history'])));
    }

    public function show(Request $request, Booking $booking)
    {
        $this->authorizeBooking($request, $booking);

        return $this->respond(new BookingResource($booking->load(['customer', 'technician', 'history.actor'])));
    }

    /** PATCH /bookings/{id} — editable details only; status has its own endpoint. */
    public function update(Request $request, Booking $booking)
    {
        $this->requireSection($request, 'bookings');
        $this->authorizeBooking($request, $booking);
        if ($request->hasAny(['status', 'technician_id', 'scheduled_on', 'final_amount'])) {
            throw WorkflowException::invalid('Use /status, /assign-technician or /invoice to change status, technician, schedule or charges.', 'use_dedicated_endpoint');
        }

        $data = $request->validate([
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'area' => ['sometimes', 'nullable', 'string', 'max:120'],
            'est_amount' => ['sometimes', 'integer', 'min:0'],
            'work_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);
        if (isset($data['est_amount']) && ! in_array($booking->status, ['New', 'Confirmed'], true)) {
            throw new WorkflowException('The estimate is locked once a technician is assigned.', 'invalid_transition');
        }

        $before = $booking->only(array_keys($data));
        $booking->update($data);
        \App\Models\AuditLog::record('booking.updated', $booking, $before, $booking->only(array_keys($data)));

        return $this->respond(new BookingResource($booking->load(['customer', 'technician'])));
    }

    public function assignTechnician(Request $request, Booking $booking)
    {
        $this->requireSection($request, 'bookings');
        $this->authorizeBooking($request, $booking);
        $data = $request->validate(['technician_id' => ['required', 'exists:technicians,id']]);

        $b = $this->workflow->assign($booking, Technician::findOrFail($data['technician_id']), $request->user());

        return $this->respond(new BookingResource($b->load(['customer', 'technician', 'history'])));
    }

    /** POST /bookings/{id}/status { status, reason?, scheduled_on?, time_slot? } */
    public function status(Request $request, Booking $booking)
    {
        $this->requireSection($request, 'bookings', 'field');
        $this->authorizeBooking($request, $booking);
        $data = $request->validate([
            'status' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:255'],
            'scheduled_on' => ['required_if:status,Rescheduled', 'nullable', 'date'],
            'time_slot' => ['required_if:status,Rescheduled', 'nullable', Rule::in(config('crm.time_slots'))],
        ]);

        $b = $data['status'] === 'Rescheduled'
            ? $this->workflow->reschedule($booking, $data['scheduled_on'], $data['time_slot'], (string) ($data['reason'] ?? ''), $request->user())
            : $this->workflow->transition($booking, $data['status'], $request->user(), $data['reason'] ?? null);

        return $this->respond(new BookingResource($b->load(['customer', 'technician', 'history'])));
    }

    /** POST /bookings/{id}/invoice { service, materials, discount } — idempotent. */
    public function invoice(Request $request, Booking $booking)
    {
        $this->requireSection($request, 'bookings');
        $this->authorizeBooking($request, $booking);

        if ($existing = $booking->invoice) {
            return $this->respond(new InvoiceResource($existing->load(['items', 'payments'])));
        }

        $data = $request->validate([
            'service' => ['required', 'integer', 'min:0'],
            'materials' => ['nullable', 'integer', 'min:0'],
            'discount' => ['nullable', 'integer', 'min:0'],
        ]);
        $result = $this->workflow->saveCharges($booking, $data['service'], $data['materials'] ?? 0, $data['discount'] ?? 0, $request->user());

        if ($result === 'pending_approval') {
            return $this->respond(['status' => 'pending_approval', 'message' => 'Charges saved; a manager or admin must approve them before invoicing.'], 202);
        }

        return $this->created(new InvoiceResource($booking->fresh()->invoice->load(['items', 'payments'])));
    }

    protected function authorizeBooking(Request $request, Booking $booking): void
    {
        abort_unless($request->user()->canAccessBooking($booking), 403, 'This booking is outside your scope.');
    }
}
