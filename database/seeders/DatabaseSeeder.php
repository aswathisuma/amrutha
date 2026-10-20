<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Automation;
use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Review;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Models\Technician;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WaTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    private const FIRST = ['Arun', 'Priya', 'Mohammed', 'Lakshmi', 'Joseph', 'Deepa', 'Suresh', 'Anitha', 'Rajesh', 'Fathima', 'Vishnu', 'Meera', 'Thomas', 'Nithya', 'Abdul', 'Divya', 'Sanjay', 'Reshma', 'Gopan', 'Aiswarya', 'Hari', 'Kavya', 'Basil', 'Sruthi', 'Manu'];

    private const LAST = ['Nair', 'Menon', 'Kurup', 'Pillai', 'Varghese', 'Thomas', 'Krishnan', 'Iqbal', 'Raj', 'Mathew', 'Das', 'Suresh', 'George', 'Antony', 'Rahman'];

    private const AREAS = ['Kakkanad', 'Edappally', 'Vyttila', 'Palarivattom', 'Aluva', 'Kaloor', 'Fort Kochi', 'Panampilly Nagar', 'Tripunithura', 'Thevara', 'Kadavanthra', 'Chottanikkara'];

    private function pick(array $a)
    {
        return $a[array_rand($a)];
    }

    private function name(): string
    {
        return $this->pick(self::FIRST).' '.$this->pick(self::LAST);
    }

    private array $usedPhones = [];

    /** Unique +91 mobile (customers.phone is unique). */
    private function phone(): string
    {
        do {
            $p = '+9199'.random_int(40, 49).random_int(100000, 999999);
        } while (isset($this->usedPhones[$p]));
        $this->usedPhones[$p] = true;

        return $p;
    }

    public function run(): void
    {
        // Spec §14: never seed prototype sample data into production.
        if (app()->isProduction()) {
            $this->command?->error('Refusing to seed demo data in production. Use `php artisan crm:create-admin` instead.');

            return;
        }

        // ---- branches -------------------------------------------------------
        $branches = collect([
            ['Kochi Central', 'Kochi', 'Ernakulam', 'Fahad K'],
            ['Kakkanad', 'Kochi', 'Ernakulam', 'Deepa Rao'],
            ['Trivandrum', 'Thiruvananthapuram', 'TVM', 'Nikhil S'],
            ['Calicut', 'Kozhikode', 'Kozhikode', 'Ansar P'],
            ['Thrissur', 'Thrissur', 'Thrissur', 'Reena M'],
        ])->map(fn ($b) => Branch::create(['name' => $b[0], 'city' => $b[1], 'district' => $b[2], 'state' => 'Kerala', 'manager_name' => $b[3]]));

        // ---- services -------------------------------------------------------
        $catalog = [
            'Home Maintenance' => ['Plumbing' => 600, 'Electrical' => 550, 'AC Service & Repair' => 900, 'Painting' => null, 'Carpentry' => null, 'Deep Cleaning' => 1800, 'Appliance Repair' => 700, 'Waterproofing' => 4500, 'General Maintenance' => null],
            'Waste Management' => ['Household Waste Collection' => 400, 'Bulk Waste Collection' => 2500, 'Construction Waste' => null, 'Garden Waste' => null, 'E-Waste Collection' => null, 'Scrap Collection' => null, 'Commercial Waste' => null],
        ];
        foreach ($catalog as $cat => $items) {
            $c = ServiceCategory::create(['name' => $cat]);
            foreach ($items as $name => $price) {
                $c->services()->create(['name' => $name, 'base_price' => $price]);
            }
        }
        $bookable = Service::whereNotNull('base_price')->get();

        // ---- technicians ----------------------------------------------------
        $techs = collect(['Vinod Kumar', 'Shaji P', 'Ratheesh N', 'Aneesh K', 'Biju Thomas', 'Salman R', 'Prakash V', 'Jomon Jose', 'Faisal M', 'Renjith S'])
            ->map(fn ($n, $i) => Technician::create([
                'name' => $n,
                'phone' => '+9198'.random_int(40, 49).random_int(10000, 99999),
                'skill' => $bookable->random()->name,
                'branch_id' => $i === 0 ? 1 : random_int(1, 5),
                'status' => $this->pick(['Available', 'On Job', 'Off Duty', 'Available', 'Available']),
                'rating' => round(3.8 + mt_rand() / mt_getrandmax() * 1.2, 1),
                'jobs_done' => random_int(40, 320),
                'load' => random_int(0, 4),
            ]));

        // ---- customers ------------------------------------------------------
        $customers = collect(range(1, 25))->map(function () {
            $name = $this->name();

            return Customer::create([
                'name' => $name,
                'phone' => $this->phone(),
                'email' => strtolower(str_replace(' ', '.', $name)).'@mail.com',
                'area' => $this->pick(self::AREAS),
                'branch_id' => random_int(1, 5),
                'type' => $this->pick(['Residential', 'Residential', 'Residential', 'Commercial']),
                'tags' => array_values(array_filter([$this->pick(['VIP', 'Repeat', 'New', '']), $this->pick(['AMC', '', ''])])),
                'created_at' => now()->subDays(random_int(5, 300)),
            ]);
        });

        // ---- bookings -------------------------------------------------------
        $descs = ['Leaking tap in kitchen', 'AC not cooling, needs gas refill', 'Two ceiling fans not working', 'Full house deep clean before move-in', 'Bulk waste from renovation', 'Washing machine drainage issue', 'Bathroom seepage on wall'];
        for ($i = 0; $i < 30; $i++) {
            $c = $customers->random();
            $s = $bookable->random();
            $st = match (true) {
                $i < 4 => 'New', $i < 8 => 'Confirmed', $i < 11 => 'Assigned', $i < 13 => 'Technician On The Way',
                $i < 15 => 'Service Started', $i < 18 => 'Service Completed', $i < 20 => 'Invoice Generated',
                $i < 23 => 'Payment Pending', $i < 28 => 'Paid', default => 'Closed',
            };
            if ($i === 5) {
                $st = 'Cancelled';
            }
            if ($i === 9) {
                $st = 'No Show';
            }
            // Active jobs are upcoming/today; finished jobs are in the past.
            $day = in_array($st, ['New', 'Confirmed', 'Assigned'], true) ? now()->addDays(random_int(0, 3))
                : (in_array($st, ['Technician On The Way', 'Service Started'], true) ? now() : now()->subDays(random_int(1, 40)));
            $est = $s->base_price + random_int(0, 3) * 200;
            $billed = in_array($st, Booking::BILLED_STATUSES, true);

            // Give technician #1 (the demo technician login) a few active jobs.
            $techId = in_array($st, ['New', 'Confirmed', 'Cancelled'], true) ? null
                : (in_array($i, [8, 11, 13, 15]) ? 1 : $techs->random()->id);

            $created = $day->copy()->subDays(random_int(0, 3))->setTime(random_int(8, 11), random_int(0, 59))->min(now()->subHour());
            $done = in_array($st, Booking::DONE_STATUSES, true);
            $completedAt = $done ? $day->copy()->setTime(15, random_int(0, 59)) : null;

            $b = Booking::create([
                'customer_id' => $c->id,
                'service_id' => $s->id,
                'service_name' => $s->name,
                'area' => $c->area,
                'branch_id' => $techId === 1 ? 1 : $c->branch_id,
                'technician_id' => $techId,
                'assigned_at' => $techId ? $created->copy()->addHours(2) : null,
                'scheduled_on' => $day,
                'time_slot' => $this->pick(config('crm.time_slots')),
                'description' => $this->pick($descs),
                'status' => $st,
                'status_reason' => match ($st) { 'Cancelled' => 'Customer travelling', 'No Show' => 'Customer not at home', default => null },
                'est_amount' => $est,
                'final_amount' => $billed ? $est + random_int(0, 4) * 150 : null,
                'material_charge' => $billed ? random_int(0, 6) * 100 : 0,
                'discount' => $billed ? random_int(0, 2) * 100 : 0,
                'completed_at' => $completedAt,
                'charges_approved_at' => $billed ? $completedAt->copy()->addHour() : null,
                'payment_status' => in_array($st, Booking::PAID_STATUSES, true) ? 'Paid' : ($st === 'Payment Pending' ? 'Pending' : '—'),
                'source' => $this->pick(config('crm.sources')),
                'created_at' => $created,
                'updated_at' => $created,
            ]);
            $this->seedHistory($b, $created, $completedAt);
        }

        // ---- invoices + payment ledger from billed bookings ------------------
        Booking::with('customer')->whereIn('status', Booking::BILLED_STATUSES)->get()->each(function (Booking $b, $i) {
            $sub = $b->subtotal();
            $tax = (int) round($sub * config('crm.gst_rate'));
            $paid = $b->payment_status === 'Paid';
            $issued = $b->charges_approved_at;
            // A few payments land today so the dashboard's "Today" figure is populated.
            $paidAt = $paid ? ($i % 3 === 0 ? now()->subHours(random_int(1, 6)) : $issued->copy()->addDay()) : null;
            $method = $this->pick(config('crm.pay_methods'));

            $inv = Invoice::create([
                'number' => 'TMP-'.$b->id,
                'booking_id' => $b->id,
                'customer_id' => $b->customer_id,
                'technician_id' => $b->technician_id,
                'service_name' => $b->service_name,
                'service_charge' => $b->amount(),
                'material_charge' => $b->material_charge,
                'discount' => $b->discount,
                'tax_rate' => config('crm.gst_rate'),
                'tax' => $tax,
                'total' => $sub + $tax,
                'amount_paid' => $paid ? $sub + $tax : 0,
                'currency' => 'INR',
                'status' => $paid ? 'Paid' : 'Pending',
                'method' => $paid ? $method : null,
                'issued_at' => $issued,
                'due_date' => $issued->copy()->addDays(config('crm.invoice_due_days')),
                'paid_at' => $paidAt,
                'customer_snapshot' => ['name' => $b->customer->name, 'phone' => $b->customer->phone, 'area' => $b->customer->area, 'email' => $b->customer->email],
            ]);
            $inv->update(['number' => Invoice::numberFor($inv->id)]);

            $inv->items()->create(['description' => 'Service charges — '.$b->service_name, 'quantity' => 1, 'unit_price' => $b->amount(), 'amount' => $b->amount()]);
            if ($b->material_charge) {
                $inv->items()->create(['description' => 'Material charges', 'quantity' => 1, 'unit_price' => $b->material_charge, 'amount' => $b->material_charge]);
            }
            if ($b->discount) {
                $inv->items()->create(['description' => 'Discount', 'quantity' => 1, 'unit_price' => -$b->discount, 'amount' => -$b->discount]);
            }
            if ($paid) {
                Payment::create(['invoice_id' => $inv->id, 'type' => 'payment', 'amount' => $inv->total, 'method' => $method, 'status' => 'succeeded', 'received_at' => $paidAt]);
            }
        });

        // ---- leads ----------------------------------------------------------
        $stages = config('crm.lead_stages');
        for ($i = 0; $i < 15; $i++) {
            Lead::create([
                'name' => $this->name(),
                'phone' => $this->phone(),
                'service_name' => $bookable->random()->name,
                'source' => $this->pick(config('crm.sources')),
                'stage' => $stages[random_int(0, $i < 3 ? 1 : ($i < 12 ? 4 : 6))],
                'value' => random_int(3, 60) * 100,
                'area' => $this->pick(self::AREAS),
                'branch_id' => random_int(1, 5),
                'note' => $this->pick(['Asked for AC service quote', 'Wants weekly waste pickup', 'Enquired via WhatsApp', 'Repeat customer, painting job', 'Needs urgent plumbing']),
                'created_at' => now()->subDays(random_int(0, 20)),
            ]);
        }

        // ---- reviews --------------------------------------------------------
        for ($i = 0; $i < 10; $i++) {
            $r = random_int(3, 5);
            Review::create([
                'customer_id' => $customers->random()->id,
                'technician_id' => $techs->random()->id,
                'service_name' => $bookable->random()->name,
                'rating' => $r,
                'comment' => $r >= 5 ? 'Excellent, on time and professional.' : ($r === 4 ? 'Good service, would use again.' : 'Okay but arrived late.'),
                'created_at' => now()->subDays(random_int(0, 30)),
            ]);
        }

        // ---- WhatsApp conversations -----------------------------------------
        $openers = [
            ['Hi', 'Need plumber for kitchen tap urgently'],
            ['Hello, do you collect construction waste?'],
            ['Hi', 'My AC is not cooling'],
            ['Book deep cleaning for Saturday please'],
            ['Is your team available in Edappally?'],
        ];
        for ($i = 0; $i < 20; $i++) {
            $conv = Conversation::create([
                'customer_id' => $customers[$i]->id,
                'unread' => $i < 4 ? random_int(1, 3) : 0,
                'status' => $this->pick(['open', 'open', 'pending', 'resolved']),
            ]);
            $t = now()->subDays(random_int(0, 6))->subMinutes(random_int(0, 600));
            $lastIn = null;
            $add = function (string $dir, string $text, bool $tpl = false) use ($conv, &$t, &$lastIn) {
                $conv->messages()->create([
                    'direction' => $dir, 'body' => $text, 'is_template' => $tpl, 'sent_at' => $t->copy(),
                    'status' => $dir === 'in' ? 'received' : $this->pick(['read', 'read', 'delivered']),
                    'status_at' => $t->copy(),
                ]);
                if ($dir === 'in') {
                    $lastIn = $t->copy();
                }
                $t->addMinutes(random_int(1, 6));
            };
            foreach ($i < 5 ? $openers[$i] : [$this->pick(['Hi', 'Hello', 'Need a technician', 'Waste pickup?', 'Booking status?'])] as $m) {
                $add('in', $m);
            }
            if ($i % 3 !== 0) {
                $add('out', 'Welcome to HayFixr 👋 How can we help you today?', true);
            }
            if ($i % 4 === 0) {
                $add('in', 'Tomorrow morning works');
                $add('out', '✅ Your booking is confirmed. Booking ID: HF-24'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), true);
            }
            $conv->update(['last_message_at' => $t->copy()->min(now()), 'last_inbound_at' => $lastIn?->min(now()), 'mode' => 'bot']);
        }

        // ---- tickets --------------------------------------------------------
        for ($i = 0; $i < 6; $i++) {
            $status = $this->pick(config('crm.ticket_statuses'));
            $created = now()->subDays(random_int(1, 10));
            $c = $customers->random();
            $t = Ticket::create([
                'number' => 'TK-'.(500 + $i + 1),
                'customer_id' => $c->id,
                'booking_id' => Booking::where('customer_id', $c->id)->value('id'),
                'issue' => $this->pick(['Technician arrived late', 'Job not completed fully', 'Overcharged on invoice', 'Rescheduling request', 'Waste not collected']),
                'description' => 'Reported by the customer over the phone.',
                'source' => $this->pick(['Phone', 'WhatsApp', 'Email']),
                'priority' => $this->pick(config('crm.ticket_priorities')),
                'status' => $status,
                'assigned_to' => $this->pick(['Sneha Pillai', 'Rahul Nair', 'Fahad K']),
                'resolved_at' => in_array($status, ['Resolved', 'Closed'], true) ? $created->copy()->addDay() : null,
                'created_at' => $created,
            ]);
            $t->events()->create(['type' => 'created', 'body' => $t->issue, 'to_value' => 'Open', 'created_at' => $created]);
            if ($status !== 'Open') {
                $t->events()->create(['type' => 'status', 'from_value' => 'Open', 'to_value' => $status, 'created_at' => $created->copy()->addHours(3)]);
            }
        }

        // ---- activity feed --------------------------------------------------
        $acts = ['contacted on WhatsApp', 'lead created', 'booking created', 'booking confirmed', 'technician assigned', 'service completed', 'invoice generated', 'payment received', 'feedback received'];
        $at = now();
        for ($i = 0; $i < 14; $i++) {
            $at = $at->copy()->subMinutes(random_int(8, 40));
            Activity::create(['subject' => $customers->random()->name, 'action' => $this->pick($acts), 'created_at' => $at, 'updated_at' => $at]);
        }

        // ---- WhatsApp templates ---------------------------------------------
        $templates = [
            ['welcome', 'Utility', '👋 Welcome to HayFixr! Your trusted Home Maintenance & Waste Management service. How can we help you today?', 'Approved'],
            ['booking_confirmed', 'Utility', "✅ Your HayFixr booking is confirmed.\nBooking ID: {{booking_id}}\nService: {{service}}\nDate: {{date}} · {{time}}\nThank you for choosing HayFixr.", 'Approved'],
            ['technician_assigned', 'Utility', "👨‍🔧 {{technician_name}} will handle your service.\nBooking ID: {{booking_id}}", 'Approved'],
            ['on_the_way', 'Utility', '🚗 Your HayFixr technician is on the way. Estimated arrival: {{eta}}.', 'Approved'],
            ['service_completed', 'Utility', '✅ Service Completed. Booking ID: {{booking_id}}. Thank you for choosing HayFixr.', 'Approved'],
            ['invoice_ready', 'Utility', "🧾 Your HayFixr invoice is ready.\nInvoice: {{invoice_number}}\nAmount: ₹{{amount}}", 'Approved'],
            ['payment_reminder', 'Utility', '💰 Payment Reminder. Amount Due: ₹{{amount}}. Please complete your payment.', 'Pending'],
            ['feedback_request', 'Marketing', '⭐ How was your HayFixr experience? Please rate our service from 1–5.', 'Approved'],
            ['followup_90d', 'Marketing', '👋 Hello {{customer_name}}! It has been {{days}} days since your last HayFixr service. Need any maintenance or waste-management help?', 'Pending'],
        ];
        foreach ($templates as [$name, $cat, $body, $status]) {
            WaTemplate::create(['name' => $name, 'category' => $cat, 'language' => 'en', 'body' => $body, 'status' => $status]);
        }

        // ---- automation rules -----------------------------------------------
        // key, trigger, action, template, status_trigger (null = scheduler), enabled
        $rules = [
            ['booking_confirmed', 'Booking Status = Confirmed', 'Send WhatsApp confirmation to customer', 'booking_confirmed', 'Confirmed', true],
            ['reminder', 'Booking Date = Tomorrow', 'Send appointment reminder', null, null, true],
            ['on_the_way', 'Technician Status = On The Way', 'Notify customer with ETA', 'on_the_way', 'Technician On The Way', true],
            ['invoice_ready', 'Service Completed + charges approved', 'Generate invoice + send WhatsApp', 'invoice_ready', 'Invoice Generated', true],
            ['payment_reminder', 'Payment Pending (24h)', 'Send payment reminder (max 3)', 'payment_reminder', null, true],
            ['feedback_request', 'Service Completed + 24h', 'Send feedback request', 'feedback_request', null, true],
            ['followup_90d', 'Service Completed + 90 days', 'Send maintenance follow-up', 'followup_90d', null, false],
        ];
        foreach ($rules as [$key, $when, $then, $tpl, $status, $on]) {
            Automation::create(['key' => $key, 'trigger' => $when, 'action' => $then, 'template' => $tpl, 'status_trigger' => $status, 'enabled' => $on, 'runs' => 0, 'max_attempts' => 3]);
        }

        // ---- users (dummy password from config('crm.demo_password')) --------
        $users = [
            ['Anjali Menon', 'anjali@hayfixr.in', 'super', null, null],
            ['Rahul Nair', 'rahul@hayfixr.in', 'admin', null, null],
            ['Fahad K', 'fahad@hayfixr.in', 'manager', 1, null],
            ['Deepa Rao', 'deepa@hayfixr.in', 'manager', 2, null],
            ['Sneha Pillai', 'sneha@hayfixr.in', 'staff', 1, null],
            ['Vinod Kumar', 'vinod@hayfixr.in', 'tech', 1, 1],
        ];
        foreach ($users as [$name, $email, $role, $branch, $tech]) {
            User::create(['name' => $name, 'email' => $email, 'password' => config('crm.demo_password'), 'role' => $role, 'branch_id' => $branch, 'technician_id' => $tech, 'status' => 'Active']);
        }

        // ---- settings -------------------------------------------------------
        Setting::put('api_status', 'Connected');
        Setting::put('currency', '₹ INR');
        Setting::put('timezone', 'IST (UTC+5:30)');
    }

    /** Status history along the happy path up to the booking's current status (exceptions branch off). */
    private function seedHistory(Booking $b, $start, $completedAt): void
    {
        $path = match ($b->status) {
            'Cancelled' => ['New', 'Confirmed', 'Cancelled'],
            'No Show' => ['New', 'Confirmed', 'Assigned', 'Technician On The Way', 'No Show'],
            default => array_slice(config('crm.statuses'), 0, array_search($b->status, config('crm.statuses'), true) + 1),
        };
        $end = $completedAt ?? $b->scheduled_on->copy()->setTime(12, 0)->min(now());
        $step = max(1, (int) ($start->diffInMinutes($end, true) / max(count($path), 1)));

        $from = null;
        foreach ($path as $n => $to) {
            BookingStatusHistory::create([
                'booking_id' => $b->id,
                'from_status' => $from,
                'to_status' => $to,
                'actor_label' => $n === 0 ? 'Staff' : ($to === 'Technician On The Way' || $to === 'Service Started' ? 'Technician' : 'Staff'),
                'reason' => in_array($to, ['Cancelled', 'No Show'], true) ? $b->status_reason : null,
                'created_at' => $start->copy()->addMinutes($step * $n)->min(now()),
            ]);
            $from = $to;
        }
    }
}
