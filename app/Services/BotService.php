<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Service;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Menu-driven WhatsApp booking bot (spec §4.4 steps 28–31).
 *
 * It never guesses: a booking is created only after the customer has picked a
 * service, a date and a time slot (and given an area if we don't have one).
 * Unclear input gets a follow-up question; repeated misses, a "talk to
 * support" request, or a staff takeover hand the chat to a human.
 */
class BotService
{
    protected const KEYWORDS = [
        '/plumb|tap|leak|pipe/' => 'Plumbing',
        '/electr|fan|switch|wiring/' => 'Electrical',
        '/\bac\b|air ?con/' => 'AC Service & Repair',
        '/clean/' => 'Deep Cleaning',
        '/applian|washing|fridge/' => 'Appliance Repair',
        '/waterproof|seepage/' => 'Waterproofing',
        '/bulk/' => 'Bulk Waste Collection',
        '/household|garbage/' => 'Household Waste Collection',
    ];

    protected const MAX_MISSES = 2;

    public function __construct(protected WhatsAppService $wa) {}

    /** @return array{reply:?string, booking:?Booking} */
    public function handle(Conversation $conv, string $text): array
    {
        $conv->loadMissing('customer');
        $t = mb_strtolower(trim($text));
        $customer = $conv->customer;

        // Opt-out / opt-in are honoured even while a human is handling the chat.
        if (in_array($t, config('crm.opt_out_keywords'), true)) {
            $customer->update(['whatsapp_opt_out' => true, 'opted_out_at' => now()]);
            Activity::log($customer->name, 'opted out of WhatsApp messages');

            return $this->reply($conv, "You've been unsubscribed from HayFixr updates. Reply START to subscribe again.", null, []);
        }
        if (in_array($t, config('crm.opt_in_keywords'), true)) {
            $customer->update(['whatsapp_opt_out' => false, 'opted_out_at' => null]);

            return $this->reply($conv, "Welcome back! You'll receive HayFixr updates again. Reply MENU to see options.", null, []);
        }

        // Staff took over: the bot stays quiet (spec §4.4 step 31).
        if ($conv->isHumanHandled()) {
            $conv->update(['needs_attention' => true]);

            return ['reply' => null, 'booking' => null];
        }

        $state = $conv->bot_state ?? [];
        if (preg_match('/^(hi|hello|hey|start|menu|0)\b/', $t)) {
            return $this->menu($conv);
        }

        return match ($state['step'] ?? null) {
            'menu' => $this->onMenu($conv, $t, $state),
            'service' => $this->onService($conv, $t, $state),
            'confirm_service' => $this->onConfirmService($conv, $t, $state),
            'date' => $this->onDate($conv, $t, $state),
            'slot' => $this->onSlot($conv, $t, $state),
            'area' => $this->onArea($conv, trim($text), $state),
            default => $this->onFreeText($conv, $t, $state),
        };
    }

    // ------------------------------------------------------------------ steps

    protected function menu(Conversation $conv): array
    {
        return $this->reply($conv, "Welcome to HayFixr 👋\n\nHow can we help you today?\n1️⃣ Home Maintenance\n2️⃣ Waste Management\n3️⃣ Service Booking\n4️⃣ Existing Booking\n5️⃣ Talk to Support", null, ['step' => 'menu']);
    }

    protected function onMenu(Conversation $conv, string $t, array $state): array
    {
        return match (true) {
            $t === '1' || str_contains($t, 'mainten') => $this->askService($conv, 1),
            $t === '2' || str_contains($t, 'waste') => $this->askService($conv, 2),
            $t === '3' || str_contains($t, 'book') => $this->askService($conv, null),
            $t === '4' || str_contains($t, 'status') => $this->bookingStatus($conv),
            $t === '5' || str_contains($t, 'support') || str_contains($t, 'agent') => $this->handoff($conv, 'Customer asked for support'),
            // A clearly named service ("need a plumber") is understood here too.
            default => $this->onFreeText($conv, $t, $state, 'Please reply with a number from 1 to 5.'),
        };
    }

    protected function askService(Conversation $conv, ?int $categoryId): array
    {
        $services = $this->bookable($categoryId);
        $list = $services->values()->map(fn ($s, $i) => ($i + 1).'. '.$s->name)->join("\n");

        return $this->reply($conv, "Which service do you need?\n{$list}\n\nReply with the number.", null, [
            'step' => 'service',
            'options' => $services->pluck('id')->values()->all(),
        ]);
    }

    protected function onService(Conversation $conv, string $t, array $state): array
    {
        $options = $state['options'] ?? [];
        $id = ctype_digit($t) ? ($options[(int) $t - 1] ?? null) : null;
        $service = $id ? Service::find($id) : $this->bookable(null)->first(fn ($s) => mb_strtolower($s->name) === $t);

        if (! $service) {
            return $this->miss($conv, $state, 'Sorry, I didn’t catch that. Reply with the number of the service, or MENU to start again.');
        }

        return $this->askDate($conv, $service);
    }

    protected function onConfirmService(Conversation $conv, string $t, array $state): array
    {
        if (in_array($t, ['yes', 'y', 'ok', '1'], true)) {
            return $this->askDate($conv, Service::findOrFail($state['service_id']));
        }
        if (in_array($t, ['no', 'n', '2'], true)) {
            return $this->askService($conv, null);
        }

        return $this->miss($conv, $state, 'Please reply YES to continue or NO to pick another service.');
    }

    protected function askDate(Conversation $conv, Service $service): array
    {
        return $this->reply($conv, "Great — {$service->name}. 📅 Which day suits you?\nReply TODAY, TOMORROW or a date like 25-10.", null, [
            'step' => 'date',
            'service_id' => $service->id,
        ]);
    }

    protected function onDate(Conversation $conv, string $t, array $state): array
    {
        $date = $this->parseDate($t);
        if (! $date) {
            return $this->miss($conv, $state, 'Please send a date within the next 60 days — e.g. TOMORROW or 25-10.');
        }

        $slots = collect(config('crm.time_slots'))->values()->map(fn ($s, $i) => ($i + 1).'. '.$s)->join("\n");

        return $this->reply($conv, "🕐 Pick a time on {$date->format('D, d M')}:\n{$slots}", null, $this->next($state, ['step' => 'slot', 'date' => $date->toDateString()]));
    }

    protected function onSlot(Conversation $conv, string $t, array $state): array
    {
        $slot = ctype_digit($t) ? (config('crm.time_slots')[(int) $t - 1] ?? null) : null;
        if (! $slot) {
            return $this->miss($conv, $state, 'Please reply with the number of a time slot.');
        }

        $state = $this->next($state, ['step' => 'area', 'slot' => $slot]);
        if (blank($conv->customer->area)) {
            return $this->reply($conv, '📍 Which area should our technician visit? (e.g. Kakkanad)', null, $state);
        }

        return $this->createBooking($conv, $state);
    }

    protected function onArea(Conversation $conv, string $text, array $state): array
    {
        if (mb_strlen($text) < 3) {
            return $this->miss($conv, $state, 'Please send your area or locality name.');
        }
        $conv->customer->update(['area' => mb_substr($text, 0, 120)]);

        return $this->createBooking($conv, $state);
    }

    /** No active step: understand a clear service request, otherwise offer the menu. */
    protected function onFreeText(Conversation $conv, string $t, array $state, string $fallback = "I can help you book a service. Reply MENU to see options, or 5 to talk to our team."): array
    {
        if ($t === '4' || str_contains($t, 'status')) {
            return $this->bookingStatus($conv);
        }

        $matches = collect(self::KEYWORDS)->filter(fn ($name, $re) => preg_match($re, $t))->unique()->values();
        if ($matches->count() === 1 && ($service = Service::where('name', $matches[0])->first())) {
            // Confirm rather than assume (spec §4.4 step 28).
            return $this->reply($conv, "Do you need *{$service->name}*? Reply YES to book it, or NO to see all services.", null, [
                'step' => 'confirm_service',
                'service_id' => $service->id,
            ]);
        }

        return $this->miss($conv, $state, $fallback);
    }

    protected function createBooking(Conversation $conv, array $state): array
    {
        $c = $conv->customer->fresh();
        $svc = Service::findOrFail($state['service_id']);

        $booking = Booking::create([
            'customer_id' => $c->id,
            'service_id' => $svc->id,
            'service_name' => $svc->name,
            'area' => $c->area,
            'branch_id' => $c->branch_id,
            'scheduled_on' => $state['date'],
            'time_slot' => $state['slot'],
            'description' => 'Requested via WhatsApp bot',
            'status' => 'New',
            'est_amount' => $svc->base_price ?? 0,
            'source' => 'WhatsApp',
        ]);
        $booking->history()->create(['from_status' => null, 'to_status' => 'New', 'actor_label' => 'WhatsApp bot']);

        Lead::create([
            'name' => $c->name,
            'phone' => $c->phone,
            'customer_id' => $c->id,
            'branch_id' => $c->branch_id,
            'service_name' => $svc->name,
            'source' => 'WhatsApp',
            'stage' => 'Booking Confirmed',
            'value' => $booking->est_amount,
            'area' => $c->area,
            'note' => 'Created by WhatsApp booking bot',
            'booking_id' => $booking->id,
        ]);
        Activity::log($c->name, 'booking '.$booking->ref.' requested via WhatsApp');

        $date = Carbon::parse($state['date'])->format('d M');

        return $this->reply($conv, "Thank you! ✅ Your booking request has been received.\n\nBooking ID: {$booking->ref}\nService: {$svc->name}\nWhen: {$date} · {$state['slot']}\n\nOur team will confirm shortly.", $booking, []);
    }

    protected function bookingStatus(Conversation $conv): array
    {
        $last = Booking::where('customer_id', $conv->customer_id)->latest()->first();

        return $this->reply($conv, $last
            ? "Your latest booking {$last->ref} ({$last->service_name}) is: {$last->status}."
            : "We couldn't find a booking on this number. Reply 3 to book a service.", null, []);
    }

    protected function handoff(Conversation $conv, string $why): array
    {
        $conv->update(['mode' => 'human', 'needs_attention' => true]);
        Activity::log($conv->customer->name, 'WhatsApp chat handed to staff · '.$why);

        return $this->reply($conv, "👩‍💼 Connecting you to our support team. Someone will reply here shortly.", null, []);
    }

    /** Ask again; after repeated misses hand over to a person instead of looping. */
    protected function miss(Conversation $conv, array $state, string $question): array
    {
        $misses = ($state['misses'] ?? 0) + 1;
        if ($misses > self::MAX_MISSES) {
            return $this->handoff($conv, 'bot could not understand the request');
        }

        return $this->reply($conv, $question, null, array_merge($state, ['misses' => $misses]));
    }

    // ---------------------------------------------------------------- helpers

    /** Advance to the next step, keeping collected answers and resetting the miss counter. */
    protected function next(array $state, array $changes): array
    {
        unset($state['misses']);

        return array_merge($state, $changes);
    }

    protected function reply(Conversation $conv, string $text, ?Booking $booking, array $state): array
    {
        $conv->update(['bot_state' => $state ?: null]);
        // A reply to the customer's own message is inside the session window — not business-initiated.
        $this->wa->send($conv->customer, $text, ['isTemplate' => false, 'businessInitiated' => false]);

        return ['reply' => $text, 'booking' => $booking];
    }

    protected function bookable(?int $categoryId): Collection
    {
        return Service::whereNotNull('base_price')
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->orderBy('category_id')->orderBy('id')
            ->get();
    }

    protected function parseDate(string $t): ?Carbon
    {
        $today = local_now()->startOfDay();
        $date = match (true) {
            $t === 'today' => $today->copy(),
            $t === 'tomorrow' => $today->copy()->addDay(),
            (bool) preg_match('#^(\d{1,2})[-/. ](\d{1,2})(?:[-/. ](\d{2,4}))?$#', $t, $m) => $this->safeDate((int) $m[1], (int) $m[2], isset($m[3]) ? (int) $m[3] : null, $today),
            default => null,
        };

        return $date && $date->between($today, $today->copy()->addDays(60)) ? $date : null;
    }

    protected function safeDate(int $d, int $mo, ?int $year, Carbon $today): ?Carbon
    {
        $y = $year ? ($year < 100 ? 2000 + $year : $year) : $today->year;
        if (! checkdate($mo, $d, $y)) {
            return null;
        }
        $date = Carbon::create($y, $mo, $d, 0, 0, 0, $today->timezone);

        // "05-01" sent in December means next January.
        return $date->lt($today) && ! $year ? $date->addYear() : $date;
    }
}
