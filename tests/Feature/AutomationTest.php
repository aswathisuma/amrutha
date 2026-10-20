<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Message;
use App\Models\Review;
use App\Models\User;
use App\Models\WaTemplate;
use App\Services\AutomationRunner;
use App\Services\BookingWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Spec §4.6 guards + §13 automation tests (duplicates, cancellations, paid invoices, opt-out). */
class AutomationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private AutomationRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runner = app(AutomationRunner::class);
        // Start from a clean slate so only our fixtures are due.
        Booking::query()->update(['scheduled_on' => now()->subYear(), 'completed_at' => null]);
        Invoice::query()->update(['status' => 'Paid', 'amount_paid' => DB::raw('total')]);
    }

    private function messagesTo(Customer $c): int
    {
        return Message::whereHas('conversation', fn ($q) => $q->where('customer_id', $c->id))->where('direction', 'out')->count();
    }

    private function tomorrowBooking(string $status = 'Confirmed'): Booking
    {
        $b = Booking::first();
        $b->update(['status' => $status, 'scheduled_on' => local_now()->addDay()->toDateString()]);

        return $b->fresh('customer');
    }

    public function test_tomorrow_reminder_is_sent_exactly_once(): void
    {
        $b = $this->tomorrowBooking();
        $before = $this->messagesTo($b->customer);

        $this->runner->runScheduled();
        $this->runner->runScheduled(); // scheduler overlap / retry

        $this->assertSame($before + 1, $this->messagesTo($b->customer));
        $this->assertSame(1, AutomationRun::where('dedupe_key', 'like', "reminder:booking:{$b->id}:%")->count());
    }

    public function test_cancelled_bookings_and_disabled_rules_get_no_reminder(): void
    {
        $b = $this->tomorrowBooking('Cancelled');
        $before = $this->messagesTo($b->customer);
        $this->runner->runScheduled();
        $this->assertSame($before, $this->messagesTo($b->customer));

        $b->update(['status' => 'Confirmed']);
        Automation::where('key', 'reminder')->update(['enabled' => false]);
        $this->runner->runScheduled();
        $this->assertSame($before, $this->messagesTo($b->customer));
    }

    public function test_opted_out_customers_are_skipped_and_logged(): void
    {
        $b = $this->tomorrowBooking();
        $b->customer->update(['whatsapp_opt_out' => true]);
        $before = $this->messagesTo($b->customer);

        $this->runner->runScheduled();

        $this->assertSame($before, $this->messagesTo($b->customer));
        $this->assertSame('skipped', AutomationRun::where('dedupe_key', 'like', "reminder:booking:{$b->id}:%")->value('status'));
    }

    public function test_payment_reminder_needs_approved_template_balance_and_respects_limit(): void
    {
        $inv = Invoice::first();
        $inv->update(['status' => 'Pending', 'amount_paid' => 0, 'issued_at' => now()->subDays(2), 'reminders_sent' => 0, 'last_reminder_at' => null]);
        $inv->booking->update(['status' => 'Payment Pending']);
        $before = $this->messagesTo($inv->customer);

        // Seeded template is still pending Meta approval → skipped, not sent.
        $this->runner->runScheduled();
        $this->assertSame($before, $this->messagesTo($inv->customer));
        $this->assertStringContainsString('not approved', AutomationRun::where('dedupe_key', "payment_reminder:invoice:{$inv->id}:1")->value('detail'));

        // Approved → sends #2… then the 24h spacing holds further reminders.
        WaTemplate::where('name', 'payment_reminder')->update(['status' => 'Approved']);
        $inv->update(['reminders_sent' => 1]);
        $this->runner->runScheduled();
        $this->runner->runScheduled();
        $this->assertSame($before + 1, $this->messagesTo($inv->customer));
        $this->assertSame(2, $inv->fresh()->reminders_sent);
        $this->assertStringContainsString(number_format($inv->balance()), Message::latest('id')->first()->body);

        // Max attempts reached → no more.
        $inv->update(['reminders_sent' => 3, 'last_reminder_at' => now()->subDays(3)]);
        $this->runner->runScheduled();
        $this->assertSame($before + 1, $this->messagesTo($inv->customer));
    }

    public function test_paid_invoices_get_no_payment_reminder(): void
    {
        WaTemplate::where('name', 'payment_reminder')->update(['status' => 'Approved']);
        $inv = Invoice::first();
        $inv->update(['issued_at' => now()->subDays(2)]); // status Paid from setUp
        $before = $this->messagesTo($inv->customer);

        $this->runner->runScheduled();

        $this->assertSame($before, $this->messagesTo($inv->customer));
    }

    public function test_feedback_request_skips_customers_who_already_reviewed(): void
    {
        $a = Booking::first();
        $b = Booking::where('customer_id', '!=', $a->customer_id)->first();
        Review::query()->delete();
        $a->update(['status' => 'Paid', 'completed_at' => now()->subDays(2)]);
        $b->update(['status' => 'Paid', 'completed_at' => now()->subDays(2)]);
        Review::create(['customer_id' => $b->customer_id, 'service_name' => 'x', 'rating' => 5, 'created_at' => now()->subDay()]);

        $this->runner->runScheduled();

        $this->assertSame('sent', AutomationRun::where('dedupe_key', "feedback:booking:{$a->id}")->value('status'));
        $this->assertSame('skipped', AutomationRun::where('dedupe_key', "feedback:booking:{$b->id}")->value('status'));
    }

    public function test_status_rule_fires_once_per_transition(): void
    {
        $admin = User::where('role', 'super')->first();
        $b = Booking::where('status', 'New')->first();
        $before = $this->messagesTo($b->customer);

        app(BookingWorkflow::class)->transition($b, 'Confirmed', $admin);

        $this->assertSame($before + 1, $this->messagesTo($b->customer));
        $run = AutomationRun::where('dedupe_key', 'like', "status:booking_confirmed:booking:{$b->id}:%")->first();
        $this->assertSame('sent', $run->status);
        $this->assertStringContainsString($b->ref, Message::latest('id')->first()->body);
    }

    public function test_command_runs_from_the_scheduler(): void
    {
        $this->tomorrowBooking();
        $this->artisan('crm:automations')->expectsOutputToContain('reminder: 1')->assertSuccessful();
        $this->artisan('crm:automations')->expectsOutput('Nothing due.')->assertSuccessful();
    }
}
