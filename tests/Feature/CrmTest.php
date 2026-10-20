<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function as(string $role): User
    {
        $user = User::where('role', $role)->firstOrFail();
        $this->actingAs($user);

        return $user;
    }

    public function test_login_with_seeded_credentials(): void
    {
        $this->post('/login', ['email' => 'anjali@hayfixr.in', 'password' => config('crm.demo_password')])->assertRedirect(route('home'));
        $this->assertAuthenticated();
    }

    public function test_every_role_can_open_each_visible_section_and_is_blocked_from_the_rest(): void
    {
        $sections = collect(config('crm.nav'))->flatMap(fn ($items) => array_keys($items));

        foreach (array_keys(config('crm.roles')) as $role) {
            $user = $this->as($role);
            foreach ($sections as $section) {
                $res = $this->get(route($section));
                $user->canSee($section) ? $res->assertOk() : $res->assertForbidden();
            }
        }
    }

    public function test_drawers_and_modals_render_as_partials(): void
    {
        $this->as('super');
        $ajax = ['X-Requested-With' => 'XMLHttpRequest'];

        $urls = [
            route('bookings.show', Booking::first()),
            route('bookings.create'),
            route('bookings.assign-form', Booking::whereNull('technician_id')->first()),
            route('leads.show', Lead::first()),
            route('leads.create'),
            route('customers.show', Customer::first()),
            route('customers.create'),
            route('invoices.show', Invoice::first()),
            route('technicians.create'),
            route('services.create'),
            route('branches.create'),
            route('support.create'),
            route('activity'),
            route('inbox.templates', Conversation::first()),
            route('bookings.status-form', [Booking::where('status', 'Confirmed')->first(), 'action' => 'Rescheduled']),
            route('bookings.charges-form', Booking::where('status', 'Service Completed')->first()),
            route('bookings.work-form', Booking::whereNotNull('technician_id')->first()),
            route('support.show', \App\Models\Ticket::first()),
            route('automation.runs', Automation::first()),
        ];
        foreach ($urls as $url) {
            $this->get($url, $ajax)->assertOk()->assertSee('data-panel=', false)->assertDontSee('<html', false);
        }

        // A direct visit falls back to the list page and re-opens the panel.
        $this->get(route('bookings.show', Booking::first()))->assertRedirect(route('bookings'))->assertSessionHas('reopen');
        $this->get(route('invoices.print', Invoice::first()))->assertOk()->assertSee(Invoice::first()->number);
    }

    public function test_approving_charges_creates_the_invoice_and_sends_whatsapp(): void
    {
        $this->as('super');
        $b = Booking::where('status', 'Service Completed')->first();
        $conv = Conversation::firstOrCreate(['customer_id' => $b->customer_id]);
        $before = $conv->messages()->count();

        // Advancing past "Service Completed" is blocked until charges are reviewed.
        $this->post(route('bookings.advance', $b))->assertSessionHas('toast', fn ($t) => $t[1] === 'err');
        $this->assertSame('Service Completed', $b->fresh()->status);

        $this->post(route('bookings.charges', $b), ['final_amount' => $b->est_amount, 'material_charge' => 0, 'discount' => 0])->assertSessionHas('toast');

        $b->refresh();
        $this->assertSame('Invoice Generated', $b->status);
        $this->assertNotNull($b->invoice);
        $this->assertSame($before + 1, $conv->messages()->count());
        $this->assertStringContainsString($b->invoice->number, \App\Models\Message::where('conversation_id', $conv->id)->latest('id')->first()->body);
    }

    public function test_disabled_automation_rule_suppresses_the_message(): void
    {
        $this->as('super');
        Automation::where('status_trigger', 'Confirmed')->update(['enabled' => false]);
        $b = Booking::where('status', 'New')->first();
        $conv = Conversation::firstOrCreate(['customer_id' => $b->customer_id]);
        $before = $conv->messages()->count();

        $this->post(route('bookings.advance', $b));

        $this->assertSame('Confirmed', $b->fresh()->status);
        $this->assertSame($before, $conv->messages()->count());
    }

    public function test_assign_cancel_with_reason_and_mark_paid(): void
    {
        $this->as('super');
        \App\Models\Technician::whereKey(2)->update(['status' => 'Available']);
        $b = Booking::whereNull('technician_id')->where('status', 'New')->first();
        Booking::where('technician_id', 2)->update(['time_slot' => '05:00 PM', 'scheduled_on' => now()->addMonth()]); // no clashes

        $this->post(route('bookings.assign', $b), ['technician_id' => 2]);
        $this->assertSame('Assigned', $b->fresh()->status);
        $this->assertSame(2, $b->fresh()->technician_id);

        // Cancelling needs a reason.
        $this->post(route('bookings.status', $b), ['status' => 'Cancelled'])->assertSessionHasErrors('reason');
        $this->post(route('bookings.status', $b), ['status' => 'Cancelled', 'reason' => 'Customer travelling']);
        $this->assertSame('Cancelled', $b->fresh()->status);
        $this->assertSame('Customer travelling', $b->history()->get()->last()->reason);

        $inv = Invoice::where('status', 'Pending')->first();
        $this->post(route('payments.paid', $inv), ['method' => 'Cash', 'idempotency_key' => 'test-key-1']);
        $this->assertSame('Paid', $inv->fresh()->status);
        $this->assertSame('Paid', $inv->booking->fresh()->status);
    }

    public function test_bot_walks_the_customer_through_a_booking(): void
    {
        $this->as('staff');
        $conv = Conversation::first();
        $conv->customer->update(['area' => 'Kakkanad']);
        $bookings = Booking::count();
        $say = fn (string $text) => $this->post(route('inbox.simulate', $conv), ['body' => $text]);
        $lastReply = fn () => \App\Models\Message::where('conversation_id', $conv->id)->latest('id')->first()->body;

        $say('Hi')->assertRedirect();
        $this->assertStringContainsString('Welcome to HayFixr', $lastReply());

        $say('I need a plumber');
        $this->assertStringContainsString('Plumbing', $lastReply());
        $this->assertSame($bookings, Booking::count(), 'the bot confirms before assuming a service');

        $say('yes');
        $say('tomorrow');
        $say('2')->assertSessionHas('toast');

        $this->assertSame($bookings + 1, Booking::count());
        $b = Booking::latest('id')->first();
        $this->assertSame('Plumbing', $b->service_name);
        $this->assertSame(config('crm.time_slots')[1], $b->time_slot);
        $this->assertSame(local_now()->addDay()->toDateString(), $b->scheduled_on->toDateString());
        $this->assertTrue(Lead::where('source', 'WhatsApp')->where('booking_id', $b->id)->exists());
    }

    public function test_lead_stage_and_conversion(): void
    {
        $this->as('staff');
        $lead = Lead::whereNull('booking_id')->first();

        $this->patch(route('leads.stage', $lead), ['stage' => 'Quotation']);
        $this->assertSame('Quotation', $lead->fresh()->stage);

        $this->post(route('leads.convert', $lead));
        $this->assertSame('Converted', $lead->fresh()->stage);
        $this->assertNotNull($lead->fresh()->booking_id);
    }

    public function test_create_forms(): void
    {
        $this->as('super');

        $this->post(route('bookings.store'), ['customer_id' => 1, 'service_id' => 1, 'time_slot' => '09:00 AM'])->assertSessionHas('toast');
        $this->post(route('customers.store'), ['name' => 'Test Person', 'phone' => '99999 99999', 'branch_id' => 1, 'type' => 'Residential'])->assertSessionHasNoErrors();
        $this->post(route('leads.store'), ['name' => 'New Lead', 'service_name' => 'Plumbing', 'source' => 'Phone'])->assertSessionHasNoErrors();
        $this->post(route('support.store'), ['customer_id' => 1, 'issue' => 'Late', 'priority' => 'High'])->assertSessionHasNoErrors();

        $conv = Conversation::first();
        $conv->update(['last_inbound_at' => now()->subHour()]); // inside the 24h window
        $this->post(route('inbox.send', $conv), ['body' => 'Hello from the team'])->assertRedirect();

        $this->assertDatabaseHas('customers', ['name' => 'Test Person', 'phone' => '+919999999999']);
        $this->assertDatabaseHas('messages', ['body' => 'Hello from the team', 'direction' => 'out']);
        $this->assertSame('human', $conv->fresh()->mode, 'an agent reply takes the chat over from the bot');

        // Duplicate phone (any formatting) is rejected.
        $this->post(route('customers.store'), ['name' => 'Dupe', 'phone' => '+91 99999-99999', 'branch_id' => 1, 'type' => 'Residential'])
            ->assertSessionHas('toast', fn ($t) => $t[1] === 'err');
        $this->assertDatabaseMissing('customers', ['name' => 'Dupe']);
    }

    public function test_branch_manager_and_technician_scoping(): void
    {
        $mgr = $this->as('manager');
        $other = Booking::where('branch_id', '!=', $mgr->branch_id)->first();
        $this->get(route('bookings.show', $other), ['X-Requested-With' => 'XMLHttpRequest'])->assertForbidden();

        $tech = $this->as('tech');
        $mine = Booking::where('technician_id', $tech->technician_id)->whereNotIn('status', ['Paid', 'Closed'])->first();
        $this->get(route('field'))->assertOk()->assertSee($mine->ref);
        $notMine = Booking::where('technician_id', '!=', $tech->technician_id)->first();
        $this->post(route('bookings.advance', $notMine))->assertForbidden();
    }

    public function test_search_and_switch_user(): void
    {
        $this->as('super');
        $c = Customer::first();
        $this->getJson(route('search', ['q' => $c->name]))->assertOk()->assertJsonFragment(['t' => $c->name]);

        $tech = User::where('role', 'tech')->first();
        $this->post(route('switch-user'), ['user_id' => $tech->id])->assertRedirect(route('field'));
        $this->assertAuthenticatedAs($tech);
    }

    public function test_whatsapp_webhook_verification_and_inbound_message(): void
    {
        $this->get('/webhook/whatsapp?hub_mode=subscribe&hub_verify_token='.config('crm.whatsapp.verify_token').'&hub_challenge=42')->assertOk()->assertSee('42');
        $this->get('/webhook/whatsapp?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=42')->assertForbidden();

        $this->postJson('/webhook/whatsapp', ['entry' => [['changes' => [['value' => [
            'contacts' => [['wa_id' => '919811122233', 'profile' => ['name' => 'Webhook User']]],
            'messages' => [['from' => '919811122233', 'id' => 'wamid.1', 'type' => 'text', 'text' => ['body' => 'Hi']]],
        ]]]]]])->assertOk();

        $c = Customer::where('phone', '+919811122233')->first();
        $this->assertSame('Webhook User', $c->name);
        $this->assertSame(2, $c->conversation->messages()->count()); // inbound + bot reply
    }
}
