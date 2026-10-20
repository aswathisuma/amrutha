<?php

namespace App\Services;

use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Review;
use App\Models\WaTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Automation engine (spec §4.6). Every execution is recorded in
 * automation_runs under a unique dedupe key, so retries, duplicate triggers
 * and overlapping scheduler runs can never send the same message twice.
 */
class AutomationRunner
{
    public function __construct(protected WhatsAppService $wa) {}

    // ------------------------------------------------------------------ status

    /** Fired after a booking status change commits. $historyId makes each transition unique. */
    public function onStatusChange(Booking $b, string $status, int $historyId): void
    {
        $b->loadMissing(['customer', 'technician', 'invoice']);
        $rule = Automation::where('status_trigger', $status)->first();

        if ($rule) {
            $this->execute($rule, "status:{$rule->key}:booking:{$b->id}:h{$historyId}", $b, function () use ($rule, $b, $status) {
                // Guard: booking must still be in the triggering status.
                if ($b->fresh()->status !== $status) {
                    return ['skipped', 'booking no longer '.$status];
                }

                return $this->sendTemplate($rule, $b->customer, $this->bookingVars($b));
            });

            return;
        }

        // Transactional update without a configurable rule.
        if ($text = $this->statusText($b, $status)) {
            $this->wa->send($b->customer, $text, ['isTemplate' => true, 'businessInitiated' => true]);
        }
    }

    // --------------------------------------------------------------- scheduled

    /** Run all time-based rules. Safe to call as often as you like. @return array<string,int> */
    public function runScheduled(): array
    {
        $counts = [];
        $tomorrow = local_now()->addDay()->toDateString();

        if ($rule = $this->rule('reminder')) {
            Booking::with(['customer', 'technician'])
                ->whereDate('scheduled_on', $tomorrow)
                ->whereIn('status', ['Confirmed', 'Assigned', 'Rescheduled'])
                ->each(function (Booking $b) use ($rule, $tomorrow, &$counts) {
                    $ran = $this->execute($rule, "reminder:booking:{$b->id}:{$tomorrow}", $b, fn () => $this->sendText(
                        $b->customer,
                        "⏰ Reminder: your HayFixr {$b->service_name} visit is tomorrow ({$b->scheduled_on->format('d M')} · {$b->time_slot}). Booking ID: {$b->ref}",
                    ));
                    $ran && $counts['reminder'] = ($counts['reminder'] ?? 0) + 1;
                });
        }

        if ($rule = $this->rule('payment_reminder')) {
            Invoice::with(['customer', 'booking'])
                ->whereIn('status', ['Pending', 'Partial'])
                ->where('issued_at', '<=', now()->subDay())
                ->where('reminders_sent', '<', $rule->max_attempts)
                ->where(fn ($q) => $q->whereNull('last_reminder_at')->orWhere('last_reminder_at', '<=', now()->subDay()))
                ->each(function (Invoice $inv) use ($rule, &$counts) {
                    $n = $inv->reminders_sent + 1;
                    $ran = $this->execute($rule, "payment_reminder:invoice:{$inv->id}:{$n}", $inv, function () use ($rule, $inv) {
                        $inv->refresh();
                        if ($inv->balance() <= 0 || in_array($inv->status, ['Paid', 'Refunded'], true) || $inv->booking?->status === 'Cancelled') {
                            return ['skipped', 'nothing due'];
                        }
                        $result = $this->sendTemplate($rule, $inv->customer, ['amount' => number_format($inv->balance())]);
                        if ($result[0] === 'sent') {
                            $inv->update(['reminders_sent' => $inv->reminders_sent + 1, 'last_reminder_at' => now()]);
                        }

                        return $result;
                    });
                    $ran && $counts['payment_reminder'] = ($counts['payment_reminder'] ?? 0) + 1;
                });
        }

        if ($rule = $this->rule('feedback_request')) {
            Booking::with('customer')
                ->whereIn('status', Booking::PAID_STATUSES)
                ->whereBetween('completed_at', [now()->subDays(30), now()->subDay()])
                ->each(function (Booking $b) use ($rule, &$counts) {
                    $ran = $this->execute($rule, "feedback:booking:{$b->id}", $b, function () use ($rule, $b) {
                        if (Review::where('customer_id', $b->customer_id)->where('created_at', '>=', $b->completed_at)->exists()) {
                            return ['skipped', 'feedback already submitted'];
                        }

                        return $this->sendTemplate($rule, $b->customer, $this->bookingVars($b));
                    });
                    $ran && $counts['feedback_request'] = ($counts['feedback_request'] ?? 0) + 1;
                });
        }

        if ($rule = $this->rule('followup_90d')) {
            Booking::with('customer')
                ->whereIn('status', Booking::PAID_STATUSES)
                ->whereBetween('completed_at', [now()->subDays(120), now()->subDays(90)])
                ->each(function (Booking $b) use ($rule, &$counts) {
                    $ran = $this->execute($rule, "followup:booking:{$b->id}", $b, function () use ($rule, $b) {
                        if (Booking::where('customer_id', $b->customer_id)->where('created_at', '>', $b->completed_at)->exists()) {
                            return ['skipped', 'newer booking supersedes follow-up'];
                        }

                        return $this->sendTemplate($rule, $b->customer, [
                            'customer_name' => $b->customer->name,
                            'days' => (string) (int) $b->completed_at->diffInDays(now()),
                        ]);
                    });
                    $ran && $counts['followup_90d'] = ($counts['followup_90d'] ?? 0) + 1;
                });
        }

        return $counts;
    }

    // ----------------------------------------------------------------- engine

    /**
     * Claim the dedupe key, then run $action (returns [status, detail]).
     * Returns false when the rule is disabled or the key was already used.
     */
    protected function execute(Automation $rule, string $dedupeKey, Model $subject, callable $action): bool
    {
        if (! $rule->enabled) {
            return false;
        }

        // INSERT … ON CONFLICT DO NOTHING: safe inside transactions on PostgreSQL too.
        $claimed = AutomationRun::query()->insertOrIgnore([
            'automation_id' => $rule->id,
            'dedupe_key' => $dedupeKey,
            'subject_type' => class_basename($subject),
            'subject_id' => $subject->getKey(),
            'status' => 'pending',
            'attempts' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if (! $claimed) {
            return false; // already handled — idempotent
        }
        $run = AutomationRun::where('dedupe_key', $dedupeKey)->first();

        try {
            [$status, $detail] = $action();
        } catch (\Throwable $e) {
            [$status, $detail] = ['failed', mb_substr($e->getMessage(), 0, 250)];
            Log::warning('Automation run failed', ['rule' => $rule->key, 'run' => $run->id, 'error' => $e->getMessage()]);
        }

        $run->update(['status' => $status, 'detail' => $detail]);
        $rule->update(['last_run_at' => now(), 'last_run_status' => $status]);
        if ($status === 'sent') {
            $rule->increment('runs');
        }

        return true;
    }

    protected function rule(string $key): ?Automation
    {
        return Automation::where('key', $key)->where('enabled', true)->first();
    }

    /** @return array{0:string,1:?string} */
    protected function sendTemplate(Automation $rule, Customer $customer, array $vars): array
    {
        $tpl = WaTemplate::where('name', $rule->template)->first();
        if (! $tpl || $tpl->status !== 'Approved') {
            return ['skipped', "template {$rule->template} not approved"];
        }

        $missing = array_diff(array_map(fn ($v) => trim($v, '{}'), $tpl->variables()), array_keys($vars));
        if ($missing) {
            return ['failed', 'missing template parameters: '.implode(', ', $missing)];
        }

        $body = strtr($tpl->body, collect($vars)->mapWithKeys(fn ($v, $k) => ['{{'.$k.'}}' => $v])->all());
        $msg = $this->wa->send($customer, $body, ['template' => $tpl->name]);

        return $msg ? ['sent', null] : ['skipped', 'customer opted out of WhatsApp'];
    }

    /** @return array{0:string,1:?string} */
    protected function sendText(Customer $customer, string $text): array
    {
        $msg = $this->wa->send($customer, $text, ['isTemplate' => true, 'businessInitiated' => true]);

        return $msg ? ['sent', null] : ['skipped', 'customer opted out of WhatsApp'];
    }

    protected function bookingVars(Booking $b): array
    {
        return [
            'booking_id' => $b->ref,
            'service' => $b->service_name,
            'date' => $b->scheduled_on->format('d M'),
            'time' => (string) $b->time_slot,
            'technician_name' => $b->technician?->name ?? 'Our technician',
            'eta' => '25 min',
            'invoice_number' => $b->invoice?->number ?? '',
            'amount' => number_format($b->invoice?->balance() ?? $b->amount()),
            'customer_name' => $b->customer->name,
        ];
    }

    protected function statusText(Booking $b, string $status): ?string
    {
        $tpl = config("crm.status_messages.$status");

        return $tpl ? strtr($tpl, [
            ':ref' => $b->ref,
            ':tech' => $b->technician?->name ?? 'Our technician',
            ':invoice' => $b->invoice?->number ?? '',
            ':amount' => money($b->invoice?->balance() ?? $b->amount()),
            ':date' => $b->scheduled_on->format('d M'),
            ':time' => (string) $b->time_slot,
        ]) : null;
    }
}
