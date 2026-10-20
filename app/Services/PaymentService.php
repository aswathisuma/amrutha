<?php

namespace App\Services;

use App\Exceptions\WorkflowException;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Append-only payment ledger (spec §4.3). Invoice balance and the booking's
 * Paid status are updated in the same transaction as the ledger entry.
 */
class PaymentService
{
    public function __construct(protected BookingWorkflow $workflow) {}

    public function record(Invoice $invoice, int $amount, string $method, ?User $actor, ?string $reference = null, ?string $idempotencyKey = null): Payment
    {
        if ($idempotencyKey && ($existing = Payment::where('idempotency_key', $idempotencyKey)->first())) {
            return $existing; // retried request — same result, no second charge
        }

        [$payment, $inv] = DB::transaction(function () use ($invoice, $amount, $method, $actor, $reference, $idempotencyKey) {
            $inv = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $balance = $inv->balance();

            if ($amount <= 0) {
                throw WorkflowException::invalid('Payment amount must be greater than zero.');
            }
            if ($balance <= 0) {
                throw new WorkflowException("{$inv->number} is already paid in full.", 'already_paid');
            }
            if ($amount > $balance) {
                throw WorkflowException::invalid('Payment of '.money($amount).' exceeds the balance of '.money($balance).'.', 'overpayment');
            }
            if ($amount < $balance && ! config('crm.allow_partial_payments')) {
                throw WorkflowException::invalid('Partial payments are disabled — collect the full '.money($balance).'.', 'partial_not_allowed');
            }

            $payment = Payment::create([
                'invoice_id' => $inv->id,
                'type' => 'payment',
                'amount' => $amount,
                'method' => $method,
                'reference' => $reference,
                'status' => 'succeeded',
                'idempotency_key' => $idempotencyKey,
                'recorded_by' => $actor?->id,
                'received_at' => now(),
            ]);

            $paid = $inv->amount_paid + $amount;
            $inv->update([
                'amount_paid' => $paid,
                'status' => $paid >= $inv->total ? 'Paid' : 'Partial',
                'method' => $method,
                'paid_at' => $paid >= $inv->total ? now() : $inv->paid_at,
            ]);
            AuditLog::record('payment.recorded', $inv, null, ['amount' => $amount, 'method' => $method, 'balance' => $inv->balance()]);

            return [$payment, $inv];
        });

        $this->syncBooking($inv, $actor);
        DB::afterCommit(fn () => Activity::log($inv->customer->name, 'payment received · '.money($amount)));

        return $payment;
    }

    /** Refunds are separate negative ledger rows; the original payment is never touched. */
    public function refund(Invoice $invoice, int $amount, string $reason, ?User $actor): Payment
    {
        if (! $actor || ! in_array($actor->role, config('crm.refund_roles'), true)) {
            throw new WorkflowException('Only admins can issue refunds.', 'forbidden', 403);
        }
        if (blank($reason)) {
            throw WorkflowException::invalid('A refund reason is required.', 'reason_required');
        }

        return DB::transaction(function () use ($invoice, $amount, $reason, $actor) {
            $inv = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($amount <= 0 || $amount > $inv->amount_paid) {
                throw WorkflowException::invalid('Refund must be between ₹1 and the amount paid ('.money($inv->amount_paid).').', 'invalid_refund');
            }

            $refund = Payment::create([
                'invoice_id' => $inv->id,
                'type' => 'refund',
                'amount' => -$amount,
                'method' => $inv->method ?? 'Cash',
                'status' => 'succeeded',
                'recorded_by' => $actor->id,
                'note' => $reason,
                'received_at' => now(),
            ]);

            $paid = $inv->amount_paid - $amount;
            $inv->update(['amount_paid' => $paid, 'status' => $paid === 0 ? 'Refunded' : 'Partial']);
            AuditLog::record('payment.refunded', $inv, null, ['amount' => $amount, 'reason' => $reason]);

            return $refund;
        });
    }

    /** Keep the booking status in step with the invoice balance. */
    protected function syncBooking(Invoice $inv, ?User $actor): void
    {
        $b = $inv->booking;
        if (! $b) {
            return;
        }

        if ($inv->balance() === 0 && in_array($b->status, ['Invoice Generated', 'Payment Pending'], true)) {
            $this->workflow->transition($b, 'Paid', $actor, null, ['final_amount' => $b->final_amount ?? $b->est_amount], true);
        } elseif ($inv->balance() > 0 && $b->status === 'Invoice Generated') {
            $this->workflow->transition($b, 'Payment Pending', $actor, 'Partial payment received');
        }
    }
}
