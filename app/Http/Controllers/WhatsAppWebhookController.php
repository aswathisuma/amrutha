<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWhatsAppEvent;
use App\Models\WebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Meta WhatsApp Cloud API webhook (spec §4.4 steps 24–26, §9).
 *
 * GET  — subscription handshake (hub.verify_token).
 * POST — verify X-Hub-Signature-256, persist each message/status once under
 *        its provider id (retries are de-duplicated), acknowledge immediately,
 *        and process on the queue.
 */
class WhatsAppWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        if ($request->isMethod('get')) {
            $ok = $request->query('hub_mode') === 'subscribe'
                && hash_equals((string) config('crm.whatsapp.verify_token'), (string) $request->query('hub_verify_token'));

            return $ok ? response($request->query('hub_challenge')) : response('Forbidden', 403);
        }

        $secret = config('crm.whatsapp.app_secret');
        if (! $secret && app()->isProduction()) {
            Log::error('WhatsApp webhook rejected: WHATSAPP_APP_SECRET is not configured');

            return response()->json(['error' => ['code' => 'not_configured', 'message' => 'Webhook signature secret missing']], 503);
        }
        if ($secret) {
            $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);
            if (! hash_equals($expected, (string) $request->header('X-Hub-Signature-256'))) {
                Log::warning('WhatsApp webhook rejected: bad signature', ['request_id' => $request->attributes->get('request_id')]);

                return response()->json(['error' => ['code' => 'invalid_signature', 'message' => 'Invalid signature']], 403);
            }
        }

        $accepted = 0;
        foreach ($request->input('entry', []) as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                $contacts = $value['contacts'] ?? [];

                foreach ($value['messages'] ?? [] as $m) {
                    $accepted += $this->store('message', $m['id'] ?? null, ['message' => $m, 'contacts' => $contacts]);
                }
                foreach ($value['statuses'] ?? [] as $s) {
                    // One message gets several status events; the pair is unique.
                    $accepted += $this->store('status', isset($s['id']) ? $s['id'].':'.($s['status'] ?? '') : null, ['status' => $s]);
                }
            }
        }

        return response()->json(['status' => 'ok', 'accepted' => $accepted]);
    }

    /** Persist once; duplicate deliveries of the same provider event are ignored. */
    protected function store(string $type, ?string $eventId, array $payload): int
    {
        if (! $eventId) {
            return 0;
        }

        $json = json_encode($payload);
        $inserted = WebhookEvent::query()->insertOrIgnore([
            'provider' => 'whatsapp',
            'event_id' => $eventId,
            'type' => $type,
            'payload_hash' => hash('sha256', $json),
            'payload' => $json,
            'status' => 'pending',
            'received_at' => now(),
        ]);
        if (! $inserted) {
            return 0;
        }

        ProcessWhatsAppEvent::dispatch(WebhookEvent::where('provider', 'whatsapp')->where('event_id', $eventId)->value('id'));

        return 1;
    }
}
