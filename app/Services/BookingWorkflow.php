<?php

namespace App\Services;

use App\Exceptions\WorkflowException;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Models\Invoice;
use App\Models\Technician;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Booking lifecycle (spec §4.2 / §5). All status changes go through
 * transition(): it validates the state machine, locks the row, writes
 * booking_status_history + audit log, and fires automations after commit.
 */
class BookingWorkflow
{
    public function __construct(protected AutomationRunner $automations) {}

    // ----------------------------------------------------------------- create

    /** Create a booking in status New (spec §4.2 steps 8–9). */
    public function create(array $data, ?User $actor, string $source = 'Manual'): Booking
    {
        $customer = \App\Models\Customer::findOrFail($data['customer_id']);
        $service = \App\Models\Service::findOrFail($data['service_id']);
        $date = Carbon::parse($data['scheduled_on'] ?? local_now()->toDateString())->startOfDay();

        if ($date->lt(local_now()->startOfDay())) {
            throw WorkflowException::invalid('The booking date cannot be in the past.');
        }
        if (! in_array($data['time_slot'] ?? null, config('crm.time_slots'), true)) {
            throw WorkflowException::invalid('Choose one of the available time slots.');
        }
        if ($actor?->isBranchScoped() && $customer->branch_id !== $actor->branch_id) {
            throw new WorkflowException('This customer belongs to another branch.', 'forbidden', 403);
        }

        return DB::transaction(function () use ($customer, $service, $date, $data, $actor, $source) {
            $b = Booking::create([
                'customer_id' => $customer->id,
                'service_id' => $service->id,
                'service_name' => $service->name,
                'area' => $data['area'] ?? $customer->area,
                'branch_id' => $data['branch_id'] ?? $customer->branch_id,
                'scheduled_on' => $date,
                'time_slot' => $data['time_slot'],
                'description' => ($data['description'] ?? null) ?: '—',
                'status' => 'New',
                'est_amount' => $data['est_amount'] ?? $service->base_price ?? 0,
                'source' => $data['source'] ?? $source,
            ]);
            BookingStatusHistory::create(['booking_id' => $b->id, 'to_status' => 'New', 'actor_user_id' => $actor?->id, 'actor_label' => $actor ? null : 'System']);
            AuditLog::record('booking.created', $b, null, $b->only(['ref', 'customer_id', 'service_name', 'scheduled_on', 'time_slot']));
            DB::afterCommit(fn () => Activity::log($customer->name, 'booking '.$b->ref.' created'));

            return $b;
        });
    }

    // ------------------------------------------------------------ transitions

    /**
     * @param  array  $attrs  extra booking columns to set in the same transaction
     * @param  bool  $system  allow statuses that are only reachable via a dedicated action
     */
    public function transition(Booking $booking, string $to, ?User $actor, ?string $reason = null, array $attrs = [], bool $system = false, ?string $actorLabel = null): Booking
    {
        $reason = $reason !== null ? trim($reason) : null;

        [$b, $history] = DB::transaction(function () use ($booking, $to, $actor, $reason, $attrs, $system, $actorLabel) {
            $b = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $from = $b->status;

            if (! in_array($to, $b->allowedTransitions(), true)) {
                throw new WorkflowException("A booking that is “{$from}” cannot move to “{$to}”.", 'invalid_transition');
            }
            if (in_array($to, config('crm.exception_statuses'), true) && blank($reason)) {
                throw WorkflowException::invalid("A reason is required to mark a booking {$to}.", 'reason_required');
            }
            if (in_array($to, config('crm.system_statuses'), true) && ! $system) {
                throw new WorkflowException($to === 'Paid'
                    ? 'Record the payment on the invoice — the booking becomes Paid once the balance is zero.'
                    : 'Review and approve the final charges to generate the invoice.', 'action_required');
            }
            if ($actor?->role === 'tech') {
                if ($b->technician_id !== $actor->technician_id) {
                    throw new WorkflowException('This job is not assigned to you.', 'forbidden', 403);
                }
                if (! in_array($to, config('crm.technician_statuses'), true)) {
                    throw new WorkflowException("Technicians can't set “{$to}”.", 'forbidden', 403);
                }
            }

            $attrs = array_merge(['technician_id' => $b->technician_id], $attrs);
            if ($to === 'Assigned' && ! $attrs['technician_id']) {
                throw WorkflowException::invalid('Assign a technician first.', 'technician_required');
            }
            if ($to === 'Service Completed') {
                $attrs['completed_at'] = now();
            }
            if ($to === 'Payment Pending') {
                $attrs['payment_status'] = 'Pending';
            }
            if ($to === 'Paid') {
                $attrs['payment_status'] = 'Paid';
            }
            if (in_array($to, ['Cancelled', 'No Show'], true) && $b->technician_id) {
                Technician::whereKey($b->technician_id)->where('load', '>', 0)->decrement('load');
            }

            $b->fill($attrs + ['status' => $to, 'status_reason' => $reason])->save();

            $history = BookingStatusHistory::create([
                'booking_id' => $b->id,
                'from_status' => $from,
                'to_status' => $to,
                'actor_user_id' => $actor?->id,
                'actor_label' => $actor ? null : ($actorLabel ?? 'System'),
                'reason' => $reason,
            ]);
            AuditLog::record('booking.status_changed', $b, ['status' => $from], ['status' => $to, 'reason' => $reason]);

            return [$b, $history];
        });

        DB::afterCommit(function () use ($b, $to, $history) {
            Activity::log($b->customer->name, 'booking '.$b->ref.' → '.strtolower($to));
            $this->automations->onStatusChange($b, $to, $history->id);
        });

        return $b;
    }

    /** Move along the happy path (Advance button). */
    public function advance(Booking $b, ?User $actor): Booking
    {
        $next = $b->nextStatus();
        if (! $next) {
            throw new WorkflowException('This booking has no further steps.', 'invalid_transition');
        }
        if ($next === 'Assigned' && ! $b->technician_id) {
            throw WorkflowException::invalid('Assign a technician to move this booking forward.', 'technician_required');
        }

        return $this->transition($b, $next, $actor);
    }

    public function cancel(Booking $b, string $reason, ?User $actor): Booking
    {
        return $this->transition($b, 'Cancelled', $actor, $reason);
    }

    public function markNoShow(Booking $b, string $reason, ?User $actor): Booking
    {
        return $this->transition($b, 'No Show', $actor, $reason);
    }

    public function reschedule(Booking $b, string $date, string $slot, string $reason, ?User $actor): Booking
    {
        $day = Carbon::parse($date)->startOfDay();
        if ($day->lt(local_now()->startOfDay())) {
            throw WorkflowException::invalid('The new date must be today or later.');
        }
        if ($b->technician_id) {
            $this->assertNoConflict($b, $b->technician_id, $day->toDateString(), $slot);
        }

        return $this->transition($b, 'Rescheduled', $actor, $reason, ['scheduled_on' => $day, 'time_slot' => $slot]);
    }

    // ------------------------------------------------------------- assignment

    /** Assign or reassign a technician after availability and conflict checks (spec §4.2 step 11). */
    public function assign(Booking $booking, Technician $tech, ?User $actor): Booking
    {
        $b = DB::transaction(function () use ($booking, $tech, $actor) {
            $b = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $t = Technician::whereKey($tech->id)->lockForUpdate()->firstOrFail();

            if (! in_array($b->status, ['New', 'Confirmed', 'Assigned', 'Rescheduled'], true)) {
                throw new WorkflowException("Technicians can't be changed once a booking is “{$b->status}”.", 'invalid_transition');
            }
            if ($t->status === 'Off Duty') {
                throw new WorkflowException("{$t->name} is off duty.", 'technician_unavailable');
            }
            $this->assertNoConflict($b, $t->id, $b->scheduled_on->toDateString(), $b->time_slot);

            $previous = $b->technician_id;
            if ($previous && $previous !== $t->id) {
                Technician::whereKey($previous)->where('load', '>', 0)->decrement('load');
            }
            if ($previous !== $t->id) {
                $t->increment('load');
            }

            $b->update(['technician_id' => $t->id, 'assigned_by' => $actor?->id, 'assigned_at' => now()]);
            AuditLog::record($previous ? 'booking.reassigned' : 'booking.assigned', $b, ['technician_id' => $previous], ['technician_id' => $t->id]);

            return $b;
        });

        // Walk New → Confirmed → Assigned so each step is in the history.
        if ($b->status === 'New') {
            $b = $this->transition($b, 'Confirmed', $actor);
        }
        if (in_array($b->status, ['Confirmed', 'Rescheduled'], true)) {
            $b = $this->transition($b, 'Assigned', $actor);
        } else {
            DB::afterCommit(fn () => Activity::log($b->customer->name, 'technician reassigned · '.$tech->name));
        }

        return $b;
    }

    protected function assertNoConflict(Booking $b, int $techId, string $date, ?string $slot): void
    {
        $clash = Booking::where('technician_id', $techId)
            ->whereKeyNot($b->id)
            ->whereDate('scheduled_on', $date)
            ->where('time_slot', $slot)
            ->whereIn('status', config('crm.active_statuses'))
            ->first();

        if ($clash) {
            throw new WorkflowException("Technician already has {$clash->ref} at {$slot} on ".Carbon::parse($date)->format('d M').'.', 'schedule_conflict');
        }
    }

    /** Technicians ranked: matching skill, same branch, lighter queue, higher rating. */
    public function rankedTechnicians(Booking $b)
    {
        return Technician::where('status', '!=', 'Off Duty')
            ->get()
            ->sortBy(fn ($t) => [
                $t->skill === $b->service_name ? 0 : 1,
                $t->branch_id === $b->branch_id ? 0 : 1,
                $t->load,
                -$t->rating,
            ])
            ->values();
    }

    // ------------------------------------------------------- charges/invoice

    /**
     * Save final charges for a completed job. Within the approval threshold (or
     * when the actor may approve) this approves them and generates the invoice;
     * otherwise the charges wait for a manager.
     *
     * @return 'invoiced'|'pending_approval'
     */
    public function saveCharges(Booking $b, int $service, int $materials, int $discount, ?User $actor): string
    {
        if ($b->status !== 'Service Completed') {
            throw new WorkflowException('Charges can only be finalised after the service is completed.', 'invalid_transition');
        }
        if ($discount > $service + $materials) {
            throw WorkflowException::invalid('Discount cannot exceed the charges.');
        }

        $before = $b->only(['final_amount', 'material_charge', 'discount']);
        $b->update([
            'final_amount' => $service,
            'material_charge' => $materials,
            'discount' => $discount,
            'charges_approved_at' => null,
            'charges_approved_by' => null,
        ]);
        AuditLog::record('booking.charges_updated', $b, $before, $b->only(['final_amount', 'material_charge', 'discount']));

        if ($b->chargesNeedApproval() && ! $this->canApprove($actor)) {
            return 'pending_approval';
        }

        $this->approveCharges($b, $actor);

        return 'invoiced';
    }

    public function approveCharges(Booking $b, ?User $actor): Invoice
    {
        if ($b->chargesNeedApproval() && ! $this->canApprove($actor)) {
            throw new WorkflowException('A manager or admin must approve charges this far from the estimate.', 'approval_required', 403);
        }
        if ($b->final_amount === null) {
            throw WorkflowException::invalid('Enter the final charges first.');
        }

        $b->update(['charges_approved_at' => now(), 'charges_approved_by' => $actor?->id]);
        AuditLog::record('booking.charges_approved', $b, null, ['subtotal' => $b->subtotal()]);

        return $this->generateInvoice($b->fresh(), $actor);
    }

    public function canApprove(?User $actor): bool
    {
        return $actor && in_array($actor->role, config('crm.charge_approver_roles'), true);
    }

    /**
     * Generate the invoice exactly once (spec §4.3). The unique booking_id
     * constraint guarantees a single invoice even under concurrent requests.
     */
    public function generateInvoice(Booking $b, ?User $actor): Invoice
    {
        if ($existing = Invoice::where('booking_id', $b->id)->first()) {
            return $existing;
        }
        if (! $b->charges_approved_at) {
            throw new WorkflowException('Final charges must be approved before invoicing.', 'approval_required');
        }

        $b->loadMissing('customer');
        $rate = config('crm.gst_rate');
        $sub = $b->subtotal();
        $tax = (int) round($sub * $rate);

        $inv = DB::transaction(function () use ($b, $rate, $sub, $tax) {
            $inserted = Invoice::query()->insertOrIgnore([
                'number' => 'TMP-'.Str::uuid(),
                'booking_id' => $b->id,
                'customer_id' => $b->customer_id,
                'technician_id' => $b->technician_id,
                'service_name' => $b->service_name,
                'service_charge' => $b->amount(),
                'material_charge' => (int) $b->material_charge,
                'discount' => (int) $b->discount,
                'tax_rate' => $rate,
                'tax' => $tax,
                'total' => $sub + $tax,
                'amount_paid' => 0,
                'currency' => config('crm.currency'),
                'status' => 'Pending',
                'issued_at' => now(),
                'due_date' => now()->addDays(config('crm.invoice_due_days'))->toDateString(),
                'customer_snapshot' => json_encode(['name' => $b->customer->name, 'phone' => $b->customer->phone, 'area' => $b->customer->area, 'email' => $b->customer->email]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $inv = Invoice::where('booking_id', $b->id)->firstOrFail();
            if (! $inserted) {
                return $inv; // another request won the race
            }

            $inv->update(['number' => Invoice::numberFor($inv->id)]);
            $inv->items()->create(['description' => 'Service charges — '.$b->service_name, 'quantity' => 1, 'unit_price' => $b->amount(), 'amount' => $b->amount()]);
            if ($b->material_charge) {
                $inv->items()->create(['description' => 'Material charges', 'quantity' => 1, 'unit_price' => $b->material_charge, 'amount' => $b->material_charge]);
            }
            if ($b->discount) {
                $inv->items()->create(['description' => 'Discount', 'quantity' => 1, 'unit_price' => -$b->discount, 'amount' => -$b->discount]);
            }
            AuditLog::record('invoice.generated', $inv, null, ['total' => $inv->total, 'booking' => $b->ref]);

            return $inv;
        });

        if ($b->fresh()->status === 'Service Completed') {
            $this->transition($b, 'Invoice Generated', $actor, null, [], true);
        }

        return $inv;
    }
}
