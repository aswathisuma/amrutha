<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Customer;
use App\Services\BotService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;

/**
 * Receives Meta WhatsApp Cloud API webhooks. Inbound text messages land in the
 * CRM inbox and are answered by the booking bot.
 */
class WhatsAppWebhookController extends Controller
{
    public function __invoke(Request $request, WhatsAppService $wa, BotService $bot)
    {
        if ($request->isMethod('get')) {
            $ok = $request->query('hub_mode') === 'subscribe'
                && hash_equals((string) config('crm.whatsapp.verify_token'), (string) $request->query('hub_verify_token'));

            return $ok ? response($request->query('hub_challenge')) : response('Forbidden', 403);
        }

        // Meta signs each POST with the app secret; reject anything that doesn't match.
        if ($secret = config('crm.whatsapp.app_secret')) {
            $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);
            abort_unless(hash_equals($expected, (string) $request->header('X-Hub-Signature-256')), 403);
        }

        foreach ($request->input('entry', []) as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                $names = collect($value['contacts'] ?? [])->pluck('profile.name', 'wa_id');

                foreach ($value['messages'] ?? [] as $m) {
                    if (($m['type'] ?? null) !== 'text') {
                        continue;
                    }
                    $phone = '+'.ltrim($m['from'], '+');
                    $customer = Customer::firstOrCreate(
                        ['phone' => $phone],
                        ['name' => $names[$m['from']] ?? $phone, 'branch_id' => 1, 'tags' => ['New']],
                    );
                    if ($customer->wasRecentlyCreated) {
                        Activity::log($customer->name, 'contacted on WhatsApp');
                    }

                    $conv = $wa->conversationFor($customer);
                    $wa->receive($conv, $m['text']['body'] ?? '', $m['id'] ?? null);
                    $bot->handle($conv->fresh('customer'), $m['text']['body'] ?? '');
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
