<?php

namespace App\Services;

use App\Exceptions\WorkflowException;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Customer, lead and ticket rules shared by the web UI and the API
 * (spec §4.1 lead-to-booking, §4.5 support tickets).
 */
class CrmService
{
    public function __construct(protected BookingWorkflow $workflow) {}

    // -------------------------------------------------------------- customers

    public function createCustomer(array $data, ?User $actor): Customer
    {
        $phone = normalize_phone($data['phone'] ?? null);
        if (! $phone) {
            throw WorkflowException::invalid('Enter a valid phone number (10 digits or +country code).', 'invalid_phone');
        }
        if ($existing = Customer::where('phone', $phone)->first()) {
            throw new WorkflowException("{$existing->name} already uses {$phone} (customer #{$existing->id}).", 'duplicate_customer');
        }

        $c = Customer::create(array_merge($data, ['phone' => $phone, 'tags' => $data['tags'] ?? ['New']]));
        AuditLog::record('customer.created', $c, null, $c->only(['name', 'phone', 'branch_id']));
        Activity::log($c->name, 'customer added');

        return $c;
    }

    // ------------------------------------------------------------------ leads

    /** Create a lead, linking it to an existing customer with the same phone (spec §4.1 step 2). */
    public function createLead(array $data, ?User $actor): Lead
    {
        $phone = normalize_phone($data['phone'] ?? null);
        $customer = $phone ? Customer::where('phone', $phone)->first() : null;

        $lead = Lead::create(array_merge($data, [
            'phone' => $phone,
            'customer_id' => $customer?->id,
            'branch_id' => $data['branch_id'] ?? $customer?->branch_id ?? $actor?->branch_id,
            'owner_user_id' => $data['owner_user_id'] ?? $actor?->id,
            'stage' => 'New Lead',
            'value' => $data['value'] ?? 0,
        ]));
        AuditLog::record('lead.created', $lead, null, $lead->only(['name', 'source', 'service_name']));
        Activity::log($lead->name, 'lead created');

        return $lead;
    }

    public function updateLead(Lead $lead, array $data, ?User $actor): Lead
    {
        $stage = $data['stage'] ?? $lead->stage;
        if ($stage === 'Lost' && blank($data['lost_reason'] ?? $lead->lost_reason)) {
            throw WorkflowException::invalid('Record why the lead was lost.', 'reason_required');
        }
        if ($stage === 'Converted' && ! $lead->booking_id) {
            throw WorkflowException::invalid('Use “Convert to booking” — a converted lead needs a booking.', 'booking_required');
        }

        $before = $lead->only(['stage', 'owner_user_id', 'note', 'lost_reason']);
        $lead->fill(array_intersect_key($data, array_flip(['stage', 'owner_user_id', 'note', 'lost_reason', 'value'])));
        if ($stage !== 'Lost') {
            $lead->lost_reason = null;
        }
        $lead->save();
        AuditLog::record('lead.updated', $lead, $before, $lead->only(['stage', 'owner_user_id', 'note', 'lost_reason']));

        return $lead;
    }

    /** Link/create the customer and create a booking (spec §4.1 step 5). */
    public function convertLead(Lead $lead, ?User $actor, array $booking = []): Booking
    {
        if ($lead->booking_id) {
            throw new WorkflowException("Lead already converted to {$lead->booking->ref}.", 'already_converted');
        }
        if ($lead->stage === 'Lost') {
            throw new WorkflowException('Reopen the lead before converting it.', 'invalid_transition');
        }

        return DB::transaction(function () use ($lead, $actor, $booking) {
            $customer = $lead->customer
                ?? Customer::findByPhone($lead->phone)
                ?? $this->createCustomer([
                    'name' => $lead->name,
                    'phone' => $lead->phone,
                    'area' => $lead->area,
                    'branch_id' => $lead->branch_id ?? $actor?->branch_id ?? 1,
                    'type' => 'Residential',
                ], $actor);

            $svc = Service::where('name', $lead->service_name)->first() ?? Service::whereNotNull('base_price')->firstOrFail();
            $b = $this->workflow->create([
                'customer_id' => $customer->id,
                'service_id' => $svc->id,
                'scheduled_on' => $booking['scheduled_on'] ?? local_now()->addDay()->toDateString(),
                'time_slot' => $booking['time_slot'] ?? config('crm.time_slots')[1],
                'description' => $lead->note,
                'est_amount' => $lead->value ?: $svc->base_price,
                'source' => $lead->source,
            ], $actor);

            $lead->update(['stage' => 'Converted', 'booking_id' => $b->id, 'customer_id' => $customer->id]);
            AuditLog::record('lead.converted', $lead, null, ['booking' => $b->ref]);

            return $b;
        });
    }

    // ---------------------------------------------------------------- tickets

    public function createTicket(array $data, ?User $actor): Ticket
    {
        if (! empty($data['booking_id']) && Booking::whereKey($data['booking_id'])->value('customer_id') !== (int) $data['customer_id']) {
            throw WorkflowException::invalid('That booking belongs to a different customer.');
        }

        return DB::transaction(function () use ($data, $actor) {
            $t = Ticket::create(array_merge($data, [
                'number' => 'TK-PENDING-'.uniqid(),
                'status' => ! empty($data['assigned_to']) ? 'Assigned' : 'Open',
            ]));
            $t->update(['number' => Ticket::numberFor($t->id)]);
            $t->events()->create(['user_id' => $actor?->id, 'type' => 'created', 'body' => $t->issue, 'to_value' => $t->status]);
            AuditLog::record('ticket.created', $t, null, $t->only(['number', 'priority', 'assigned_to']));
            Activity::log($t->customer->name, 'complaint logged · '.$t->number);

            return $t;
        });
    }

    public function changeTicketStatus(Ticket $t, string $status, ?User $actor, ?string $note = null): Ticket
    {
        if ($status === $t->status) {
            return $t;
        }
        if ($status === 'Closed' && ! in_array($t->status, ['Resolved', 'Closed'], true) && blank($note)) {
            throw WorkflowException::invalid('Resolve the ticket (or add a closing note) before closing it.', 'reason_required');
        }

        $from = $t->status;
        $t->update(['status' => $status, 'resolved_at' => in_array($status, ['Resolved', 'Closed'], true) ? ($t->resolved_at ?? now()) : null]);
        $t->events()->create(['user_id' => $actor?->id, 'type' => 'status', 'from_value' => $from, 'to_value' => $status, 'body' => $note]);
        AuditLog::record('ticket.status_changed', $t, ['status' => $from], ['status' => $status]);

        return $t;
    }

    public function commentOnTicket(Ticket $t, string $body, ?User $actor): void
    {
        $t->events()->create(['user_id' => $actor?->id, 'type' => 'comment', 'body' => $body]);
    }
}
