<?php

namespace App\Jobs;

use App\Models\Activity;
use App\Models\Customer;
use App\Models\Message;
use App\Models\WebhookEvent;
use App\Services\BotService;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

/** Applies one stored webhook event: inbound message → inbox + bot; status → delivery tracking. */
class ProcessWhatsAppEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public array $backoff = [5, 30, 120];

    public function __construct(public int $eventId) {}

    public function handle(WhatsAppService $wa, BotService $bot): void
    {
        $event = WebhookEvent::find($this->eventId);
        if (! $event || $event->status === 'processed') {
            return;
        }

        $result = $event->type === 'message'
            ? $this->message($event->payload, $wa, $bot)
            : $this->status($event->payload['status'] ?? []);

        $event->update(['status' => $result === true ? 'processed' : 'ignored', 'error' => is_string($result) ? $result : null, 'processed_at' => now()]);
    }

    public function failed(\Throwable $e): void
    {
        WebhookEvent::whereKey($this->eventId)->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 250)]);
    }

    protected function message(array $payload, WhatsAppService $wa, BotService $bot): bool|string
    {
        $m = $payload['message'] ?? [];
        $phone = normalize_phone($m['from'] ?? null);
        if (! $phone) {
            return 'unparseable sender';
        }

        $name = collect($payload['contacts'] ?? [])->firstWhere('wa_id', $m['from'])['profile']['name'] ?? $phone;
        $body = match ($m['type'] ?? null) {
            'text' => $m['text']['body'] ?? '',
            'button' => $m['button']['text'] ?? '',
            'interactive' => $m['interactive']['button_reply']['title'] ?? $m['interactive']['list_reply']['title'] ?? '',
            default => '['.($m['type'] ?? 'unknown').' message]',
        };

        [$conv, $isText] = DB::transaction(function () use ($phone, $name, $wa, $m, $body) {
            $customer = Customer::firstOrCreate(['phone' => $phone], ['name' => $name, 'branch_id' => 1, 'tags' => ['New']]);
            if ($customer->wasRecentlyCreated) {
                Activity::log($customer->name, 'contacted on WhatsApp');
            }
            $conv = $wa->conversationFor($customer);
            $wa->receive($conv, $body, $m['id'] ?? null);

            return [$conv, in_array($m['type'] ?? null, ['text', 'button', 'interactive'], true)];
        });

        if ($isText) {
            $bot->handle($conv->fresh('customer'), $body);
        } else {
            $conv->update(['needs_attention' => true]); // media etc. → a person should look
        }

        return true;
    }

    protected function status(array $s): bool|string
    {
        $msg = Message::where('wa_message_id', $s['id'] ?? '')->first();
        if (! $msg) {
            return 'unknown message id';
        }

        $applied = $msg->applyStatus(
            $s['status'] ?? 'sent',
            isset($s['errors'][0]['code']) ? (string) $s['errors'][0]['code'] : null,
            $s['errors'][0]['title'] ?? null,
        );

        return $applied ?: 'stale status (out of order)';
    }
}
