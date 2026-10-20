<?php

namespace App\Jobs;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Delivers one outbound message through the Meta Cloud API (spec §9).
 * Transient failures (network, 429, 5xx) retry with exponential backoff and
 * jitter; permanent 4xx errors fail immediately and keep the provider error.
 */
class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public Message $message) {}

    /** @return list<int> seconds between attempts */
    public function backoff(): array
    {
        return array_map(fn ($s) => $s + random_int(0, (int) ($s / 2)), [10, 30, 90, 270]);
    }

    public function handle(): void
    {
        $msg = $this->message->fresh(['conversation.customer']);
        if (! $msg || $msg->status !== 'queued') {
            return; // already sent/failed — keep the job idempotent
        }

        $cfg = config('crm.whatsapp');
        $msg->increment('attempts');
        $to = ltrim($msg->conversation->customer->phone, '+');

        $res = Http::withToken($cfg['token'])
            ->timeout(15)
            ->post("https://graph.facebook.com/v21.0/{$cfg['phone_id']}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'text',
                'text' => ['body' => $msg->body],
            ]);

        if ($res->successful()) {
            $msg->update(['wa_message_id' => $res->json('messages.0.id')]);
            $msg->applyStatus('sent');

            return;
        }

        $code = (string) ($res->json('error.code') ?? $res->status());
        $error = (string) ($res->json('error.message') ?? 'HTTP '.$res->status());

        // Permanent: bad request / policy / auth errors — retrying will not help.
        if ($res->clientError() && $res->status() !== 429) {
            $msg->applyStatus('failed', $code, mb_substr($error, 0, 250));
            Log::warning('WhatsApp send rejected', ['message_id' => $msg->id, 'code' => $code]);

            return;
        }

        throw new \RuntimeException("WhatsApp send failed ({$code}): {$error}");
    }

    public function failed(\Throwable $e): void
    {
        $this->message->fresh()?->applyStatus('failed', 'retries_exhausted', mb_substr($e->getMessage(), 0, 250));
    }
}
