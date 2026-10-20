<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Service;

/**
 * Menu-driven WhatsApp booking bot. Replies to an inbound message and, when the
 * customer names a service, creates a booking and a lead in the CRM.
 */
class BotService
{
    protected const SERVICE_KEYWORDS = [
        '/plumb/' => 'Plumbing',
        '/electr/' => 'Electrical',
        '/\bac\b|air ?con/' => 'AC Service & Repair',
        '/clean/' => 'Deep Cleaning',
        '/applian|washing/' => 'Appliance Repair',
        '/waterproof|seepage|leak/' => 'Waterproofing',
    ];

    public function __construct(protected WhatsAppService $wa) {}

    /** @return array{reply:string, booking:?Booking} */
    public function handle(Conversation $conv, string $text): array
    {
        $t = mb_strtolower(trim($text));
        $booking = null;

        if (preg_match('/^(hi|hello|hey|start)\b/', $t)) {
            $reply = "Welcome to HayFixr 👋\n\nHow can we help you today?\n1️⃣ Home Maintenance\n2️⃣ Waste Management\n3️⃣ Service Booking\n4️⃣ Existing Booking\n5️⃣ Talk to Support";
        } elseif ($t === '1' || str_contains($t, 'mainten')) {
            $reply = "Great! Which service?\n• Plumbing\n• Electrical\n• AC Repair\n• Cleaning\n\nReply with the service name.";
        } elseif ($t === '2' || str_contains($t, 'waste')) {
            $reply = "Waste Management 🗑️\nReply with: Household / Bulk / Construction / E-Waste";
        } elseif ($t === '3' || str_contains($t, 'book')) {
            $reply = "Let's book your service. Please share:\n1. Your name\n2. Service needed\n3. Address\n4. Preferred date & time";
        } elseif ($t === '4' || str_contains($t, 'status')) {
            $last = Booking::where('customer_id', $conv->customer_id)->latest()->first();
            $reply = $last
                ? "Your latest booking {$last->ref} ({$last->service_name}) is: {$last->status}."
                : "We couldn't find a booking on this number. Reply 3 to book a service.";
        } elseif ($serviceName = $this->matchService($t)) {
            $booking = $this->createBooking($conv, $serviceName);
            $reply = "Thank you! ✅ Your booking request has been received.\n\nBooking ID: {$booking->ref}\nOur team will confirm shortly.";
        } else {
            $reply = "I'll connect you to our support team. Meanwhile, reply 'Hi' to see the menu.";
        }

        $this->wa->send($conv->customer, $reply, true);

        return ['reply' => $reply, 'booking' => $booking];
    }

    protected function matchService(string $t): ?string
    {
        foreach (self::SERVICE_KEYWORDS as $re => $name) {
            if (preg_match($re, $t)) {
                return $name;
            }
        }

        return null;
    }

    protected function createBooking(Conversation $conv, string $serviceName): Booking
    {
        $c = $conv->customer;
        $svc = Service::where('name', $serviceName)->first();

        $booking = Booking::create([
            'customer_id' => $c->id,
            'service_id' => $svc?->id,
            'service_name' => $serviceName,
            'area' => $c->area,
            'branch_id' => $c->branch_id,
            'scheduled_on' => now()->addDay(),
            'time_slot' => '11:00 AM',
            'description' => 'Created via WhatsApp bot',
            'status' => 'New',
            'est_amount' => $svc?->base_price ?? 600,
            'source' => 'WhatsApp',
        ]);

        Lead::create([
            'name' => $c->name,
            'phone' => $c->phone,
            'service_name' => $serviceName,
            'source' => 'WhatsApp',
            'stage' => 'Booking Confirmed',
            'value' => $booking->est_amount,
            'area' => $c->area,
            'note' => 'Auto-created by booking bot',
            'booking_id' => $booking->id,
        ]);

        Activity::log($c->name, 'booking created via WhatsApp bot');

        return $booking;
    }
}
