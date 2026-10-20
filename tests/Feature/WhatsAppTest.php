<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppMessage;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\User;
use App\Models\WaTemplate;
use App\Models\WebhookEvent;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** Spec §4.4 / §9 / §13 webhook tests. */
class WhatsAppTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private const SECRET = 'test-app-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['crm.whatsapp.app_secret' => self::SECRET]);
    }

    private function inbound(string $from, string $text, string $id): array
    {
        return ['entry' => [['changes' => [['value' => [
            'contacts' => [['wa_id' => $from, 'profile' => ['name' => 'Webhook User']]],
            'messages' => [['from' => $from, 'id' => $id, 'type' => 'text', 'text' => ['body' => $text]]],
        ]]]]]];
    }

    private function postSigned(array $payload, ?string $secret = self::SECRET)
    {
        $body = json_encode($payload);
        $sig = 'sha256='.hash_hmac('sha256', $body, $secret ?? 'wrong');

        return $this->call('POST', '/webhook/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => $sig], $body);
    }

    public function test_verification_handshake(): void
    {
        $this->get('/webhook/whatsapp?hub_mode=subscribe&hub_verify_token='.config('crm.whatsapp.verify_token').'&hub_challenge=42')->assertOk()->assertSee('42');
        $this->get('/webhook/whatsapp?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=42')->assertForbidden();
    }

    public function test_invalid_signature_is_rejected_and_nothing_stored(): void
    {
        $this->postSigned($this->inbound('919811122233', 'Hi', 'wamid.bad'), 'not-the-secret')->assertForbidden();
        $this->assertSame(0, WebhookEvent::count());
        $this->assertNull(Customer::where('phone', '+919811122233')->first());
    }

    public function test_valid_message_creates_customer_conversation_and_bot_reply(): void
    {
        $this->postSigned($this->inbound('919811122233', 'Hi', 'wamid.1'))->assertOk()->assertJson(['accepted' => 1]);

        $c = Customer::where('phone', '+919811122233')->firstOrFail();
        $this->assertSame('Webhook User', $c->name);
        $msgs = $c->conversation->messages;
        $this->assertSame(['in', 'out'], $msgs->pluck('direction')->all());
        $this->assertStringContainsString('Welcome to HayFixr', $msgs->last()->body);
        $this->assertSame('processed', WebhookEvent::first()->status);
        $this->assertTrue($c->conversation->withinSessionWindow());
    }

    public function test_duplicate_delivery_is_processed_once(): void
    {
        $payload = $this->inbound('919811122233', 'Hi', 'wamid.dup');
        $this->postSigned($payload)->assertJson(['accepted' => 1]);
        $this->postSigned($payload)->assertOk()->assertJson(['accepted' => 0]);

        $this->assertSame(1, WebhookEvent::count());
        $this->assertSame(1, Message::where('direction', 'in')->where('wa_message_id', 'wamid.dup')->count());
    }

    public function test_status_updates_apply_in_order_and_ignore_stale_ones(): void
    {
        $msg = Conversation::first()->messages()->create(['direction' => 'out', 'body' => 'x', 'status' => 'sent', 'wa_message_id' => 'wamid.out1', 'sent_at' => now()]);
        $status = fn ($s, $extra = []) => ['entry' => [['changes' => [['value' => ['statuses' => [['id' => 'wamid.out1', 'status' => $s] + $extra]]]]]]];

        $this->postSigned($status('read'));
        $this->assertSame('read', $msg->fresh()->status);

        $this->postSigned($status('delivered')); // arrives late
        $this->assertSame('read', $msg->fresh()->status);
        $this->assertSame('ignored', WebhookEvent::where('event_id', 'wamid.out1:delivered')->value('status'));

        $this->postSigned($status('failed', ['errors' => [['code' => 131026, 'title' => 'Receiver incapable']]]));
        $this->assertSame('failed', $msg->fresh()->status);
        $this->assertSame('131026', $msg->fresh()->error_code);
    }

    public function test_stop_opts_out_and_suppresses_business_messages(): void
    {
        $this->postSigned($this->inbound('919811122233', 'STOP', 'wamid.stop'));
        $c = Customer::where('phone', '+919811122233')->first();
        $this->assertTrue($c->whatsapp_opt_out);

        // Business-initiated (template/automation) sends are suppressed…
        $this->assertNull(app(WhatsAppService::class)->send($c, 'Promo', ['template' => 'welcome']));

        // …and START re-subscribes.
        $this->postSigned($this->inbound('919811122233', 'START', 'wamid.start'));
        $this->assertFalse($c->fresh()->whatsapp_opt_out);
    }

    public function test_agent_takeover_pauses_the_bot(): void
    {
        $agent = User::where('role', 'staff')->first();
        $this->postSigned($this->inbound('919811122233', 'Hi', 'wamid.a'));
        $conv = Customer::where('phone', '+919811122233')->first()->conversation;

        app(WhatsAppService::class)->sendAgentMessage($conv->fresh('customer'), 'Hello, this is Sneha', $agent);
        $this->assertSame('human', $conv->fresh()->mode);

        $before = $conv->messages()->count();
        $this->postSigned($this->inbound('919811122233', 'Hi', 'wamid.b'));

        $this->assertSame($before + 1, $conv->messages()->count(), 'only the inbound message — no bot reply');
        $this->assertTrue($conv->fresh()->needs_attention);
    }

    public function test_free_form_reply_outside_24h_window_is_blocked(): void
    {
        $this->actingAs(User::where('role', 'staff')->first());
        $conv = Conversation::first();
        $conv->update(['last_inbound_at' => now()->subHours(30)]);

        $this->post(route('inbox.send', $conv), ['body' => 'Late reply'])->assertSessionHas('toast', fn ($t) => $t[1] === 'err');
        $this->assertDatabaseMissing('messages', ['body' => 'Late reply']);

        // Approved template is still allowed; unapproved is not.
        $this->post(route('inbox.template', $conv), ['template_id' => WaTemplate::where('name', 'welcome')->value('id')])->assertSessionHas('toast', fn ($t) => $t[1] === 'wa');
        $this->post(route('inbox.template', $conv), ['template_id' => WaTemplate::where('status', 'Pending')->value('id')])->assertSessionHas('toast', fn ($t) => $t[1] === 'err');
    }

    public function test_bot_does_not_book_from_ambiguous_text_and_hands_off(): void
    {
        $this->postSigned($this->inbound('919811122233', 'something is broken', 'wamid.x1'));
        $this->postSigned($this->inbound('919811122233', 'please help', 'wamid.x2'));
        $this->postSigned($this->inbound('919811122233', '???', 'wamid.x3'));

        $c = Customer::where('phone', '+919811122233')->first();
        $this->assertSame(0, Booking::where('customer_id', $c->id)->count());
        $this->assertSame('human', $c->conversation->mode);
        $this->assertTrue($c->conversation->needs_attention);
    }

    public function test_outbound_is_queued_and_delivered_through_cloud_api(): void
    {
        config(['crm.whatsapp.token' => 'test-token', 'crm.whatsapp.phone_id' => '123']);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.sent1']]])]);

        $msg = app(WhatsAppService::class)->send(Customer::first(), 'Hello', ['businessInitiated' => false]);

        $this->assertSame('sent', $msg->fresh()->status); // sync queue in tests
        $this->assertSame('wamid.sent1', $msg->fresh()->wa_message_id);
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer test-token') && $r['to'] === ltrim(Customer::first()->phone, '+'));
    }

    public function test_permanent_api_errors_fail_without_retry(): void
    {
        config(['crm.whatsapp.token' => 'test-token', 'crm.whatsapp.phone_id' => '123']);
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['code' => 131047, 'message' => 'Re-engagement message']], 400)]);

        $msg = app(WhatsAppService::class)->send(Customer::first(), 'Hello', ['businessInitiated' => false]);

        $this->assertSame('failed', $msg->fresh()->status);
        $this->assertSame('131047', $msg->fresh()->error_code);
        Http::assertSentCount(1);
    }

    public function test_messages_are_queued_not_sent_inline_when_a_worker_is_used(): void
    {
        config(['crm.whatsapp.token' => 'test-token']);
        Queue::fake();

        $msg = app(WhatsAppService::class)->send(Customer::first(), 'Hello', ['businessInitiated' => false]);

        $this->assertSame('queued', $msg->status);
        Queue::assertPushed(SendWhatsAppMessage::class, fn ($job) => $job->message->is($msg));
    }
}
