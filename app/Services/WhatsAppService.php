<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Records WhatsApp traffic in the CRM inbox and, when an access token is
 * configured, delivers outbound messages through the Meta Cloud API.
 */
class WhatsAppService
{
    public function conversationFor(Customer $customer): Conversation
    {
        return Conversation::firstOrCreate(
            ['customer_id' => $customer->id],
            ['status' => 'open', 'unread' => 0, 'last_message_at' => now()],
        );
    }

    public function send(Customer $customer, string $text, bool $template = false): Message
    {
        $conv = $this->conversationFor($customer);
        $msg = $conv->messages()->create([
            'direction' => 'out',
            'body' => $text,
            'is_template' => $template,
            'sent_at' => now(),
            'wa_message_id' => $this->deliver($customer->phone, $text),
        ]);
        $conv->update(['unread' => 0, 'last_message_at' => now()]);

        return $msg;
    }

    public function receive(Conversation $conv, string $text, ?string $waId = null): Message
    {
        $msg = $conv->messages()->create([
            'direction' => 'in',
            'body' => $text,
            'sent_at' => now(),
            'wa_message_id' => $waId,
        ]);
        $conv->increment('unread');
        $conv->update(['last_message_at' => now(), 'status' => 'open']);

        return $msg;
    }

    /** Send through the Cloud API. Returns the WhatsApp message id, or null when not configured / failed. */
    protected function deliver(string $phone, string $text): ?string
    {
        $cfg = config('crm.whatsapp');
        if (empty($cfg['token'])) {
            return null;
        }

        try {
            $res = Http::withToken($cfg['token'])
                ->timeout(10)
                ->post("https://graph.facebook.com/v21.0/{$cfg['phone_id']}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => ltrim($phone, '+'),
                    'type' => 'text',
                    'text' => ['body' => $text],
                ]);

            return $res->json('messages.0.id');
        } catch (\Throwable $e) {
            Log::warning('WhatsApp delivery failed', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
