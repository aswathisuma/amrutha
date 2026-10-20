<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Automation;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Technician;

/**
 * Moves bookings through their lifecycle and fires the side effects the
 * automation engine is responsible for (WhatsApp updates, invoices, activity).
 */
class BookingWorkflow
{
    public function __construct(protected WhatsAppService $wa) {}

    public function advance(Booking $b): ?string
    {
        $next = $b->nextStatus();
        if (! $next) {
            return null;
        }

        $b->status = $next;

        if ($next === 'Assigned' && ! $b->technician_id) {
            $b->technician_id = $this->bestTechnician($b)?->id;
        }
        if ($next === 'Payment Pending') {
            $b->payment_status = 'Pending';
        }
        if ($next === 'Paid') {
            $b->payment_status = 'Paid';
            $b->final_amount ??= $b->est_amount;
        }
        $b->save();

        if ($next === 'Invoice Generated') {
            $this->ensureInvoice($b);
        }
        if ($next === 'Paid' && ($inv = $b->invoice) && ! $inv->isPaid()) {
            $inv->update(['status' => 'Paid', 'method' => $inv->method ?: 'UPI', 'paid_at' => now()]);
        }

        $this->notify($b);
        Activity::log($b->customer->name, 'booking '.$b->ref.' → '.strtolower($next));

        return $next;
    }

    public function assign(Booking $b, Technician $t): void
    {
        $b->technician_id = $t->id;
        if (in_array($b->status, ['New', 'Confirmed'], true)) {
            $b->status = 'Assigned';
        }
        $b->save();
        $t->increment('load');

        $this->notify($b->fresh(), 'Assigned');
        Activity::log($b->customer->name, 'technician assigned');
    }

    public function cancel(Booking $b): void
    {
        $b->update(['status' => 'Cancelled']);
        Activity::log($b->customer->name, 'booking '.$b->ref.' cancelled');
    }

    /** Technicians ranked by workload, excluding off-duty staff and preferring the booking's branch. */
    public function rankedTechnicians(Booking $b)
    {
        return Technician::where('status', '!=', 'Off Duty')
            ->get()
            ->sortBy(fn ($t) => [$t->branch_id === $b->branch_id ? 0 : 1, $t->load, -$t->rating])
            ->values();
    }

    public function bestTechnician(Booking $b): ?Technician
    {
        return $this->rankedTechnicians($b)->first();
    }

    public function ensureInvoice(Booking $b): Invoice
    {
        if ($b->invoice) {
            return $b->invoice;
        }

        $svc = $b->final_amount ?? $b->est_amount;
        $tax = (int) round($svc * config('crm.gst_rate'));

        $inv = Invoice::create([
            'number' => Invoice::nextNumber(),
            'booking_id' => $b->id,
            'customer_id' => $b->customer_id,
            'technician_id' => $b->technician_id,
            'service_name' => $b->service_name,
            'service_charge' => $svc,
            'tax' => $tax,
            'total' => $svc + $tax,
            'status' => 'Pending',
            'issued_at' => now(),
        ]);
        $b->setRelation('invoice', $inv);
        Activity::log($b->customer->name, 'invoice generated');

        return $inv;
    }

    public function markInvoicePaid(Invoice $inv, string $method = 'UPI'): void
    {
        $inv->update(['status' => 'Paid', 'method' => $method, 'paid_at' => now()]);

        if (($b = $inv->booking) && in_array($b->status, ['Invoice Generated', 'Payment Pending', 'Service Completed'], true)) {
            $b->update(['status' => 'Paid', 'payment_status' => 'Paid', 'final_amount' => $b->final_amount ?? $b->est_amount]);
        }
        Activity::log($inv->customer->name, 'payment received');
    }

    /** Send the WhatsApp update for a status, honouring any automation rule tied to it. */
    protected function notify(Booking $b, ?string $status = null): void
    {
        $status ??= $b->status;
        $tpl = config("crm.status_messages.$status");
        if (! $tpl) {
            return;
        }

        $rule = Automation::where('status_trigger', $status)->first();
        if ($rule && ! $rule->enabled) {
            return;
        }

        $text = strtr($tpl, [
            ':ref' => $b->ref,
            ':tech' => $b->technician?->name ?? 'Our technician',
            ':invoice' => $b->invoice?->number ?? '',
            ':amount' => money($b->invoice?->total ?? $b->amount()),
        ]);

        $this->wa->send($b->customer, $text, true);
        $rule?->increment('runs');
    }
}
