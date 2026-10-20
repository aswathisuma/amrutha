<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Technician;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Spec §8 REST contract + §13 authorization tests. */
class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function as(string $role): User
    {
        $u = User::where('role', $role)->firstOrFail();
        Sanctum::actingAs($u);

        return $u;
    }

    public function test_login_issues_expiring_token_and_me_returns_scope(): void
    {
        $res = $this->postJson('/api/v1/auth/login', ['email' => 'fahad@hayfixr.in', 'password' => config('crm.demo_password')])
            ->assertOk()
            ->assertJsonStructure(['data' => ['token', 'expires_at', 'user' => ['role', 'branch_scope', 'permissions']], 'request_id']);
        $this->assertNotNull($res->json('data.expires_at'));

        $this->withToken($res->json('data.token'))->getJson('/api/v1/me')
            ->assertOk()->assertJsonPath('data.role', 'manager')->assertJsonPath('data.branch_scope', [1]);

        $this->postJson('/api/v1/auth/login', ['email' => 'fahad@hayfixr.in', 'password' => 'nope'])
            ->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_error_envelopes_and_request_id(): void
    {
        $this->getJson('/api/v1/bookings')->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthenticated')
            ->assertJsonStructure(['request_id'])
            ->assertHeader('X-Request-Id');

        $this->as('super');
        $this->getJson('/api/v1/bookings/999999')->assertStatus(404)->assertJsonPath('error.code', 'not_found');
        $this->postJson('/api/v1/bookings', [])->assertStatus(422)->assertJsonStructure(['error' => ['details' => ['customer_id']]]);
        $this->getJson('/api/v1/bookings?sort=password')->assertStatus(400);
        $this->getJson('/api/v1/bookings', ['X-Request-Id' => 'trace-abc-123'])->assertHeader('X-Request-Id', 'trace-abc-123')->assertJsonPath('request_id', 'trace-abc-123');
    }

    public function test_every_role_against_endpoints(): void
    {
        $matrix = [
            '/api/v1/dashboard' => ['super' => 200, 'admin' => 200, 'manager' => 200, 'staff' => 200, 'tech' => 403],
            '/api/v1/bookings' => ['super' => 200, 'admin' => 200, 'manager' => 200, 'staff' => 200, 'tech' => 200],
            '/api/v1/leads' => ['super' => 200, 'admin' => 200, 'manager' => 200, 'staff' => 200, 'tech' => 403],
            '/api/v1/invoices' => ['super' => 200, 'admin' => 200, 'manager' => 200, 'staff' => 403, 'tech' => 403],
            '/api/v1/automation-rules' => ['super' => 200, 'admin' => 200, 'manager' => 403, 'staff' => 403, 'tech' => 403],
            '/api/v1/reports/operations' => ['super' => 200, 'admin' => 200, 'manager' => 200, 'staff' => 403, 'tech' => 403],
            '/api/v1/support/tickets' => ['super' => 200, 'admin' => 200, 'manager' => 403, 'staff' => 200, 'tech' => 403],
        ];
        foreach ($matrix as $url => $expect) {
            foreach ($expect as $role => $status) {
                $this->as($role);
                $this->getJson($url)->assertStatus($status);
            }
        }
    }

    public function test_cross_branch_and_unassigned_technician_access_is_denied(): void
    {
        $mgr = $this->as('manager');
        $other = Booking::where('branch_id', '!=', $mgr->branch_id)->firstOrFail();
        $this->getJson("/api/v1/bookings/{$other->id}")->assertForbidden();
        $ids = collect($this->getJson('/api/v1/bookings?limit=100')->json('data'))->pluck('branch_id')->unique()->values()->all();
        $this->assertSame([$mgr->branch_id], $ids);

        $tech = $this->as('tech');
        $notMine = Booking::where('technician_id', '!=', $tech->technician_id)->firstOrFail();
        $this->getJson("/api/v1/bookings/{$notMine->id}")->assertForbidden();
        $this->postJson("/api/v1/bookings/{$notMine->id}/status", ['status' => 'Service Started'])->assertForbidden();
    }

    public function test_pagination_meta(): void
    {
        $this->as('super');
        $this->getJson('/api/v1/customers?limit=10&page=2')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.page', 2)
            ->assertJsonPath('meta.total', Customer::count());
        $this->getJson('/api/v1/customers?limit=500')->assertStatus(422);
    }

    public function test_booking_lifecycle_over_the_api(): void
    {
        $this->as('super');
        $tech = Technician::first();
        $tech->update(['status' => 'Available']);
        Booking::where('technician_id', $tech->id)->update(['scheduled_on' => now()->addYear()]);

        $id = $this->postJson('/api/v1/bookings', [
            'customer_id' => Customer::first()->id,
            'service_id' => Service::where('name', 'Plumbing')->value('id'),
            'scheduled_on' => local_now()->addDays(3)->toDateString(),
            'time_slot' => '11:00 AM',
        ])->assertCreated()->assertJsonPath('data.status', 'New')->json('data.id');

        $this->postJson("/api/v1/bookings/$id/status", ['status' => 'Service Completed'])->assertStatus(409)->assertJsonPath('error.code', 'invalid_transition');
        $this->postJson("/api/v1/bookings/$id/status", ['status' => 'Cancelled'])->assertStatus(422)->assertJsonPath('error.code', 'reason_required');

        $this->postJson("/api/v1/bookings/$id/assign-technician", ['technician_id' => $tech->id])->assertOk()->assertJsonPath('data.status', 'Assigned');
        foreach (['Technician On The Way', 'Service Started', 'Service Completed'] as $s) {
            $this->postJson("/api/v1/bookings/$id/status", ['status' => $s])->assertOk()->assertJsonPath('data.status', $s);
        }

        $inv = $this->postJson("/api/v1/bookings/$id/invoice", ['service' => 600, 'materials' => 100], ['Idempotency-Key' => 'inv-key-0001'])
            ->assertCreated()->assertJsonPath('data.total', 826)->json('data');
        // Same key → identical replay; new key → still one invoice.
        $this->postJson("/api/v1/bookings/$id/invoice", ['service' => 600, 'materials' => 100], ['Idempotency-Key' => 'inv-key-0001'])
            ->assertCreated()->assertHeader('Idempotent-Replayed', 'true')->assertJsonPath('data.id', $inv['id']);
        $this->postJson("/api/v1/bookings/$id/invoice", [], ['Idempotency-Key' => 'inv-key-0002'])->assertOk()->assertJsonPath('data.id', $inv['id']);

        $pay = fn ($key, $amount) => $this->postJson("/api/v1/invoices/{$inv['id']}/payments", ['amount' => $amount, 'method' => 'UPI'], ['Idempotency-Key' => $key]);
        $pay('pay-key-0001', 826)->assertCreated()->assertJsonPath('data.invoice.status', 'Paid');
        $pay('pay-key-0001', 826)->assertCreated()->assertHeader('Idempotent-Replayed', 'true');
        $pay('pay-key-0001', 1)->assertStatus(409)->assertJsonPath('error.code', 'idempotency_key_reused');
        $this->assertSame(1, Payment::where('invoice_id', $inv['id'])->count());

        $this->getJson("/api/v1/bookings/$id")->assertOk()
            ->assertJsonPath('data.status', 'Paid')
            ->assertJsonCount(8, 'data.history'); // New, Confirmed, Assigned, On The Way, Started, Completed, Invoice, Paid
    }

    public function test_duplicate_customer_returns_409(): void
    {
        $this->as('staff');
        $existing = Customer::first();
        $this->postJson('/api/v1/customers', ['name' => 'X', 'phone' => substr($existing->phone, 3), 'branch_id' => 1, 'type' => 'Residential'])
            ->assertStatus(409)->assertJsonPath('error.code', 'duplicate_customer');
    }

    public function test_lead_lost_needs_reason_and_conversion_links_customer(): void
    {
        $this->as('staff');
        $lead = $this->postJson('/api/v1/leads', ['name' => 'API Lead', 'phone' => '9811100000', 'service_name' => 'Plumbing', 'source' => 'Website'])
            ->assertCreated()->assertJsonPath('data.phone_e164', '+919811100000')->json('data');

        $this->patchJson("/api/v1/leads/{$lead['id']}", ['stage' => 'Lost'])->assertStatus(422)->assertJsonPath('error.code', 'reason_required');
        $this->patchJson("/api/v1/leads/{$lead['id']}", ['stage' => 'Quotation'])->assertOk()->assertJsonPath('data.stage', 'Quotation');

        $this->postJson("/api/v1/leads/{$lead['id']}/convert")->assertCreated()->assertJsonPath('data.status', 'New');
        $this->assertNotNull(Customer::where('phone', '+919811100000')->first());
        $this->postJson("/api/v1/leads/{$lead['id']}/convert")->assertStatus(409)->assertJsonPath('error.code', 'already_converted');
    }

    public function test_conversations_and_automation_rules(): void
    {
        $this->as('admin');
        $conv = $this->getJson('/api/v1/conversations')->assertOk()->json('data.0');
        $this->getJson("/api/v1/conversations/{$conv['id']}/messages")->assertOk()->assertJsonStructure(['data' => [['direction', 'status']], 'meta']);
        $this->postJson("/api/v1/conversations/{$conv['id']}/messages", ['template' => 'welcome'])->assertCreated()->assertJsonPath('data.template_name', 'welcome');

        $rule = $this->getJson('/api/v1/automation-rules')->json('data.0');
        $this->patchJson("/api/v1/automation-rules/{$rule['id']}", ['enabled' => false])->assertOk()->assertJsonPath('data.enabled', false);
        $this->postJson('/api/v1/automation-rules', ['key' => 'started', 'status_trigger' => 'Service Started', 'template' => 'payment_reminder'])
            ->assertStatus(422); // template not approved
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'x@hayfixr.in', 'password' => 'bad']);
        }
        $this->postJson('/api/v1/auth/login', ['email' => 'x@hayfixr.in', 'password' => 'bad'])->assertStatus(429)->assertJsonPath('error.code', 'rate_limited');
    }
}
