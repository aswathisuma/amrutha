<?php

namespace Tests\Feature;

use App\Exceptions\WorkflowException;
use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Technician;
use App\Models\User;
use App\Services\BookingWorkflow;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Spec §5 state machine, §4.2 assignment/charges, §4.3 invoice + ledger. */
class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private BookingWorkflow $wf;

    private PaymentService $pay;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wf = app(BookingWorkflow::class);
        $this->pay = app(PaymentService::class);
        $this->admin = User::where('role', 'super')->first();
        $this->actingAs($this->admin);
    }

    private function newBooking(array $attrs = []): Booking
    {
        $b = $this->wf->create([
            'customer_id' => Customer::first()->id,
            'service_id' => Service::where('name', 'Plumbing')->first()->id,
            'scheduled_on' => local_now()->addDays(10)->toDateString(),
            'time_slot' => '09:00 AM',
        ], $this->admin);

        return $attrs ? tap($b)->update($attrs) : $b;
    }

    private function freeTech(): Technician
    {
        $t = Technician::first();
        $t->update(['status' => 'Available']);

        return $t;
    }

    public function test_happy_path_records_history_with_actor(): void
    {
        $b = $this->newBooking();
        $this->wf->assign($b, $this->freeTech(), $this->admin); // New → Confirmed → Assigned
        $b = $this->wf->advance($b->fresh(), $this->admin); // → On The Way
        $b = $this->wf->advance($b, $this->admin); // → Service Started
        $b = $this->wf->advance($b, $this->admin); // → Service Completed

        $this->assertSame('Service Completed', $b->status);
        $this->assertNotNull($b->completed_at);
        $this->assertSame(
            [null, 'New', 'Confirmed', 'Assigned', 'Technician On The Way', 'Service Started'],
            $b->history()->pluck('from_status')->all(),
        );
        $this->assertSame($this->admin->id, $b->history()->get()->last()->actor_user_id);
    }

    public function test_arbitrary_jumps_are_rejected(): void
    {
        $b = $this->newBooking();

        try {
            $this->wf->transition($b, 'Service Completed', $this->admin);
            $this->fail('Jump should be rejected');
        } catch (WorkflowException $e) {
            $this->assertSame('invalid_transition', $e->errorCode);
            $this->assertSame(409, $e->status);
        }
        $this->assertSame('New', $b->fresh()->status);
        $this->assertSame(1, BookingStatusHistory::where('booking_id', $b->id)->count());
    }

    public function test_exception_statuses_need_a_reason(): void
    {
        $b = $this->newBooking();

        try {
            $this->wf->transition($b, 'Cancelled', $this->admin, '  ');
            $this->fail('Cancelled without a reason');
        } catch (WorkflowException $e) {
            $this->assertSame('reason_required', $e->errorCode);
            $this->assertSame(422, $e->status);
        }
        $this->assertSame('New', $b->fresh()->status);
    }

    public function test_paid_and_invoice_statuses_need_their_dedicated_actions(): void
    {
        $b = $this->newBooking(['status' => 'Service Completed']);
        $this->expectException(WorkflowException::class);
        $this->wf->transition($b, 'Invoice Generated', $this->admin);
    }

    public function test_technician_can_only_set_field_statuses_on_own_jobs(): void
    {
        $techUser = User::where('role', 'tech')->first();
        $mine = $this->newBooking(['status' => 'Assigned', 'technician_id' => $techUser->technician_id]);
        $this->wf->transition($mine, 'Technician On The Way', $techUser);
        $this->assertSame('Technician On The Way', $mine->fresh()->status);

        $other = $this->newBooking(['status' => 'Assigned', 'technician_id' => Technician::where('id', '!=', $techUser->technician_id)->value('id')]);
        try {
            $this->wf->transition($other, 'Technician On The Way', $techUser);
            $this->fail('Tech moved someone else\'s job');
        } catch (WorkflowException $e) {
            $this->assertSame(403, $e->status);
        }

        $confirmed = $this->newBooking(['status' => 'New', 'technician_id' => $techUser->technician_id]);
        $this->expectException(WorkflowException::class);
        $this->wf->transition($confirmed, 'Confirmed', $techUser); // office-only status
    }

    public function test_assignment_rejects_off_duty_and_double_booked_technicians(): void
    {
        $t = $this->freeTech();
        $first = $this->newBooking();
        $this->wf->assign($first, $t, $this->admin);
        $this->assertSame($this->admin->id, $first->fresh()->assigned_by);

        $clash = $this->newBooking(); // same day + slot
        try {
            $this->wf->assign($clash, $t, $this->admin);
            $this->fail('Double booking allowed');
        } catch (WorkflowException $e) {
            $this->assertSame('schedule_conflict', $e->errorCode);
        }

        $t->update(['status' => 'Off Duty']);
        $later = $this->newBooking(['time_slot' => '05:00 PM']);
        $this->expectExceptionMessage('off duty');
        $this->wf->assign($later, $t, $this->admin);
    }

    public function test_reassignment_moves_load_between_technicians(): void
    {
        [$a, $b2] = Technician::take(2)->get();
        Technician::whereIn('id', [$a->id, $b2->id])->update(['status' => 'Available', 'load' => 0]);
        Booking::whereIn('technician_id', [$a->id, $b2->id])->update(['scheduled_on' => now()->addYear()]);

        $b = $this->newBooking();
        $this->wf->assign($b, $a->fresh(), $this->admin);
        $this->wf->assign($b->fresh(), $b2->fresh(), $this->admin);

        $this->assertSame(0, $a->fresh()->load);
        $this->assertSame(1, $b2->fresh()->load);
        $this->assertSame($b2->id, $b->fresh()->technician_id);
    }

    public function test_large_charge_changes_need_manager_approval(): void
    {
        $staff = User::where('role', 'staff')->first();
        $b = $this->newBooking(['status' => 'Service Completed', 'est_amount' => 1000]);

        $this->assertSame('pending_approval', $this->wf->saveCharges($b, 2000, 0, 0, $staff));
        $this->assertNull($b->fresh()->invoice);

        try {
            $this->wf->approveCharges($b->fresh(), $staff);
            $this->fail('Staff approved over-threshold charges');
        } catch (WorkflowException $e) {
            $this->assertSame('approval_required', $e->errorCode);
        }

        $inv = $this->wf->approveCharges($b->fresh(), User::where('role', 'manager')->first());
        $this->assertSame(2000 + 360, $inv->total);
        $this->assertSame('Invoice Generated', $b->fresh()->status);
    }

    public function test_small_charge_changes_invoice_immediately_with_items_and_tax(): void
    {
        $staff = User::where('role', 'staff')->first();
        $b = $this->newBooking(['status' => 'Service Completed', 'est_amount' => 1000]);

        $this->assertSame('invoiced', $this->wf->saveCharges($b, 1000, 200, 100, $staff));
        $inv = $b->fresh()->invoice;

        $this->assertSame(1100, $inv->items()->sum('amount'));
        $this->assertSame(198, $inv->tax); // 18% of 1100
        $this->assertSame(1298, $inv->total);
        $this->assertSame(Invoice::numberFor($inv->id), $inv->number);
        $this->assertSame($b->customer->name, $inv->customer_snapshot['name']);

        // Later price/customer changes don't touch the issued invoice.
        $billedName = $b->customer->name;
        Service::where('name', 'Plumbing')->update(['base_price' => 9999]);
        $b->customer->update(['name' => 'Renamed']);
        $this->assertSame(1298, $inv->fresh()->total);
        $this->assertSame($billedName, $inv->fresh()->billedTo()['name']);
    }

    public function test_invoice_generation_is_idempotent(): void
    {
        $b = $this->newBooking(['status' => 'Service Completed']);
        $this->wf->saveCharges($b, 600, 0, 0, $this->admin);
        $first = $b->fresh()->invoice;

        $again = $this->wf->generateInvoice($b->fresh(), $this->admin);

        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, Invoice::where('booking_id', $b->id)->count());
    }

    public function test_partial_payment_then_balance_marks_booking_paid(): void
    {
        $b = $this->newBooking(['status' => 'Service Completed']);
        $this->wf->saveCharges($b, 1000, 0, 0, $this->admin);
        $inv = $b->fresh()->invoice; // total 1180

        $this->pay->record($inv, 500, 'Cash', $this->admin, null, 'k1');
        $this->assertSame('Partial', $inv->fresh()->status);
        $this->assertSame('Payment Pending', $b->fresh()->status);

        $this->pay->record($inv->fresh(), 680, 'UPI', $this->admin, 'UPI123', 'k2');
        $this->assertSame('Paid', $inv->fresh()->status);
        $this->assertSame(0, $inv->fresh()->balance());
        $this->assertSame('Paid', $b->fresh()->status);
        $this->assertSame(1180, (int) Payment::where('invoice_id', $inv->id)->sum('amount'));
    }

    public function test_payment_idempotency_overpayment_and_refunds(): void
    {
        $b = $this->newBooking(['status' => 'Service Completed']);
        $this->wf->saveCharges($b, 1000, 0, 0, $this->admin);
        $inv = $b->fresh()->invoice;

        $p1 = $this->pay->record($inv, 1180, 'UPI', $this->admin, null, 'same-key');
        $p2 = $this->pay->record($inv->fresh(), 1180, 'UPI', $this->admin, null, 'same-key');
        $this->assertSame($p1->id, $p2->id);
        $this->assertSame(1, Payment::where('invoice_id', $inv->id)->count());

        try {
            $this->pay->record($inv->fresh(), 1, 'UPI', $this->admin);
            $this->fail('Overpayment accepted');
        } catch (WorkflowException $e) {
            $this->assertSame('already_paid', $e->errorCode);
        }

        // Refund: staff can't, admin can; original row untouched.
        try {
            $this->pay->refund($inv->fresh(), 100, 'goodwill', User::where('role', 'staff')->first());
            $this->fail('Staff refunded');
        } catch (WorkflowException $e) {
            $this->assertSame(403, $e->status);
        }
        $this->pay->refund($inv->fresh(), 180, 'Partial goodwill refund', $this->admin);

        $this->assertSame(1180, $p1->fresh()->amount);
        $this->assertSame(-180, Payment::where('type', 'refund')->value('amount'));
        $this->assertSame('Partial', $inv->fresh()->status);
        $this->assertSame(1000, $inv->fresh()->amount_paid);

        $this->expectException(\LogicException::class);
        $p1->update(['amount' => 1]); // ledger rows are immutable
    }

    public function test_reschedule_requires_reason_and_future_date(): void
    {
        $b = $this->newBooking(['status' => 'Confirmed']);

        try {
            $this->wf->reschedule($b, local_now()->subDay()->toDateString(), '09:00 AM', 'x', $this->admin);
            $this->fail('Past date accepted');
        } catch (WorkflowException $e) {
            $this->assertSame(422, $e->status);
        }

        $b = $this->wf->reschedule($b, local_now()->addDays(20)->toDateString(), '03:00 PM', 'Customer away', $this->admin);
        $this->assertSame('Rescheduled', $b->status);
        $this->assertSame('03:00 PM', $b->time_slot);
        $this->assertContains('Confirmed', $b->allowedTransitions());
    }

    public function test_phone_normalisation(): void
    {
        $this->assertSame('+919876543210', normalize_phone('98765 43210'));
        $this->assertSame('+919876543210', normalize_phone('+91-98765-43210'));
        $this->assertSame('+919876543210', normalize_phone('09876543210'));
        $this->assertSame('+447700900123', normalize_phone('0044 7700 900123'));
        $this->assertNull(normalize_phone('12345'));
    }
}
