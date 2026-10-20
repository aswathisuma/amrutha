<?php

namespace App\Services;

use App\Exceptions\WorkflowException;
use App\Jobs\SendWhatsAppMessage;
use App\Models\WaTemplate;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\User;

/**
 * Records WhatsApp traffic in the CRM inbox. Outbound messages are stored as
 * "queued" and delivered by a retrying queue job when the Cloud API is
 * configured; without a token they stay local (status "local").
 */
class WhatsAppService
{
    public function conversationFor(Customer $customer): Conversation
    {
        return Conversation::firstOrCreate(
            ['customer_id' => $customer->id],
            ['status' => 'open', 'unread' => 0, 'mode' => 'bot', 'last_message_at' => now()],
        );
    }

    /**
     * Queue an outbound message.
     *
     * Options: template (name of the approved template, marks the message as a
     * template send), user (agent who sent it), businessInitiated (automation or
     * status update — suppressed for opted-out customers).
     * Returns null when the message was suppressed.
     */
    public function send(Customer $customer, string $text, array $opts = []): ?Message
    {
        $template = $opts['template'] ?? null;
        $isTemplate = $template !== null || ($opts['isTemplate'] ?? false);
        $businessInitiated = $opts['businessInitiated'] ?? $isTemplate;

        if ($businessInitiated && ! $customer->canReceiveWhatsApp()) {
            return null;
        }

        $conv = $this->conversationFor($customer);
        $live = filled(config('crm.whatsapp.token'));

        $msg = $conv->messages()->create([
            'direction' => 'out',
            'body' => $text,
            'is_template' => $isTemplate,
            'template_name' => $template,
            'sent_by' => ($opts['user'] ?? null) instanceof User ? $opts['user']->id : null,
            'status' => $live ? 'queued' : 'local',
            'sent_at' => now(),
            'status_at' => now(),
        ]);
        $conv->update(['unread' => 0, 'last_message_at' => now()]);

        if ($live) {
            SendWhatsAppMessage::dispatch($msg)->afterCommit();
        }

        return $msg;
    }

    /**
     * Agent reply from the inbox. Free-form text is only allowed inside the
     * 24h customer-service window; sending one takes the chat over from the bot.
     */
    public function sendAgentMessage(Conversation $conv, string $text, User $agent): Message
    {
        if (! $conv->withinSessionWindow()) {
            throw new WorkflowException('The 24-hour reply window has closed — send an approved template instead.', 'session_window_closed');
        }

        $this->takeOver($conv, $agent);

        return $this->send($conv->customer, $text, ['user' => $agent, 'businessInitiated' => false]);
    }

    /** Send an approved template, filling the variables the CRM can resolve. */
    public function sendTemplate(Conversation $conv, WaTemplate $tpl, User $agent): Message
    {
        if ($tpl->status !== 'Approved') {
            throw new WorkflowException("Template “{$tpl->name}” is not approved by Meta yet.", 'template_not_approved');
        }
        $c = $conv->customer;
        if (! $c->canReceiveWhatsApp()) {
            throw new WorkflowException("{$c->name} has opted out of WhatsApp messages.", 'opted_out');
        }

        $last = $c->bookings()->latest()->first();
        $vars = [
            '{{customer_name}}' => $c->name,
            '{{booking_id}}' => $last?->ref,
            '{{service}}' => $last?->service_name,
            '{{date}}' => $last?->scheduled_on?->format('d M'),
            '{{time}}' => $last?->time_slot,
            '{{technician_name}}' => $last?->technician?->name ?? 'Our technician',
            '{{eta}}' => '25 min',
            '{{invoice_number}}' => $last?->invoice?->number,
            '{{amount}}' => $last?->invoice ? number_format($last->invoice->balance()) : null,
        ];
        $missing = array_filter($tpl->variables(), fn ($v) => blank($vars[$v] ?? null));
        if ($missing) {
            throw WorkflowException::invalid('This template needs '.implode(', ', $missing).' — the customer has no matching booking/invoice.', 'missing_parameters');
        }

        return $this->send($c, strtr($tpl->body, array_filter($vars)), ['template' => $tpl->name, 'user' => $agent]);
    }

    /** Staff takeover: the bot stops replying until the chat is handed back (spec §4.4 step 31). */
    public function takeOver(Conversation $conv, User $agent): void
    {
        $conv->update(['mode' => 'human', 'assigned_user_id' => $conv->assigned_user_id ?? $agent->id, 'needs_attention' => false, 'bot_state' => null]);
    }

    public function release(Conversation $conv): void
    {
        $conv->update(['mode' => 'bot', 'assigned_user_id' => null, 'needs_attention' => false, 'bot_state' => null]);
    }

    public function receive(Conversation $conv, string $text, ?string $waId = null): Message
    {
        $msg = $conv->messages()->create([
            'direction' => 'in',
            'body' => $text,
            'status' => 'received',
            'sent_at' => now(),
            'wa_message_id' => $waId,
        ]);
        $conv->increment('unread');
        $conv->update(['last_message_at' => now(), 'last_inbound_at' => now(), 'status' => 'open']);

        return $msg;
    }
}
