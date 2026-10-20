<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\WaTemplate;
use App\Services\BotService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;

class InboxController extends Controller
{
    public function __construct(protected WhatsAppService $wa, protected BotService $bot) {}

    public function index(Request $request)
    {
        $conversations = Conversation::with(['customer', 'latestMessage'])
            ->when($request->query('q'), fn ($q, $term) => $q->whereHas('customer', fn ($c) => $c->where('name', 'like', "%$term%")->orWhere('phone', 'like', "%$term%")))
            ->orderByDesc('last_message_at')
            ->get();

        $active = null;
        if ($id = $request->integer('c')) {
            $active = Conversation::with(['customer', 'messages'])->find($id);
        } elseif (! $request->boolean('list')) {
            $active = $conversations->first()?->load('messages');
        }
        if ($active && $active->unread) {
            $active->update(['unread' => 0]);
        }

        return view('inbox.index', [
            'conversations' => $conversations,
            'active' => $active,
            'unread' => $conversations->sum('unread'),
        ]);
    }

    public function openForCustomer(Customer $customer)
    {
        return redirect()->route('inbox', ['c' => $this->wa->conversationFor($customer)->id]);
    }

    public function openForLead(Lead $lead)
    {
        $customer = Customer::where('phone', $lead->phone)->first();
        if (! $customer) {
            return $this->done('No WhatsApp contact for this lead yet — convert it to create one', 'warn');
        }

        return $this->openForCustomer($customer);
    }

    /** Agent reply sent from the inbox composer. */
    public function send(Request $request, Conversation $conversation)
    {
        $text = $request->validate(['body' => ['required', 'string', 'max:4096']])['body'];
        $this->wa->send($conversation->customer, $text);

        return redirect()->route('inbox', ['c' => $conversation->id]);
    }

    /** Template picker (modal behind the "Send template" button). */
    public function templates(Conversation $conversation)
    {
        return $this->panel('inbox.templates', [
            'conv' => $conversation->load('customer'),
            'templates' => WaTemplate::where('status', 'Approved')->orderBy('id')->get(),
        ], route('inbox', ['c' => $conversation->id]));
    }

    public function sendTemplate(Request $request, Conversation $conversation)
    {
        $tpl = WaTemplate::findOrFail($request->integer('template_id'));
        $c = $conversation->customer;
        $last = $c->bookings()->latest()->first();

        // Fill the variables we can resolve from the CRM; leave the rest visible.
        $body = strtr($tpl->body, [
            '{{customer_name}}' => $c->name,
            '{{booking_id}}' => $last?->ref ?? '—',
            '{{service}}' => $last?->service_name ?? '—',
            '{{date}}' => $last?->scheduled_on?->format('d M') ?? '—',
            '{{time}}' => $last?->time_slot ?? '—',
            '{{technician_name}}' => $last?->technician?->name ?? 'Our technician',
            '{{eta}}' => '25 min',
        ]);
        $this->wa->send($c, $body, true);

        return redirect()->route('inbox', ['c' => $conversation->id])->with('toast', ['Template "'.$tpl->name.'" sent', 'wa']);
    }

    /** Demo: inject an inbound customer message and let the bot answer it. */
    public function simulate(Request $request, Conversation $conversation)
    {
        $text = $request->input('body', 'Hi') ?: 'Hi';
        $this->wa->receive($conversation, $text);
        $result = $this->bot->handle($conversation->fresh('customer'), $text);

        $redirect = redirect()->route('inbox', ['c' => $conversation->id]);
        if ($result['booking']) {
            $redirect->with('toast', ['Booking '.$result['booking']->ref.' + lead auto-created from WhatsApp bot', 'wa']);
        }

        return $redirect;
    }
}
