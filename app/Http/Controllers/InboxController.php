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
        $conversations = $this->visible($request)->with(['customer', 'latestMessage'])
            ->when($request->query('q'), fn ($q, $term) => $q->whereHas('customer', fn ($c) => $c->where('name', 'like', "%$term%")->orWhere('phone', 'like', '%'.preg_replace('/\D/', '', $term).'%')))
            ->orderByDesc('needs_attention')
            ->orderByDesc('last_message_at')
            ->get();

        $active = null;
        if ($id = $request->integer('c')) {
            $active = $this->visible($request)->with(['customer', 'messages.sender', 'assignee'])->find($id);
        } elseif (! $request->boolean('list')) {
            $active = $conversations->first()?->load(['messages.sender', 'assignee']);
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

    public function openForCustomer(Request $request, Customer $customer)
    {
        $u = $request->user();
        abort_if($u->isBranchScoped() && $customer->branch_id !== $u->branch_id, 403);
        abort_if($u->role === 'tech' && ! $customer->bookings()->where('technician_id', $u->technician_id)->exists(), 403);

        return redirect()->route('inbox', ['c' => $this->wa->conversationFor($customer)->id]);
    }

    public function openForLead(Request $request, Lead $lead)
    {
        $customer = $lead->customer ?? Customer::findByPhone($lead->phone);
        if (! $customer) {
            return $this->done('No WhatsApp contact for this lead yet — convert it to create one', 'warn');
        }

        return $this->openForCustomer($request, $customer);
    }

    /** Agent reply — 24h window enforced; sending takes the chat over from the bot. */
    public function send(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);
        $text = $request->validate(['body' => ['required', 'string', 'max:4096']])['body'];
        $this->wa->sendAgentMessage($conversation->load('customer'), $text, $request->user());

        return redirect()->route('inbox', ['c' => $conversation->id]);
    }

    /** Template picker (modal behind the "Send template" button). */
    public function templates(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);

        return $this->panel('inbox.templates', [
            'conv' => $conversation->load('customer'),
            'templates' => WaTemplate::orderByRaw("CASE WHEN status = 'Approved' THEN 0 ELSE 1 END")->orderBy('id')->get(),
        ], route('inbox', ['c' => $conversation->id]));
    }

    public function sendTemplate(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);
        $tpl = WaTemplate::findOrFail($request->integer('template_id'));
        $this->wa->sendTemplate($conversation->load('customer'), $tpl, $request->user());

        return redirect()->route('inbox', ['c' => $conversation->id])->with('toast', ['Template "'.$tpl->name.'" queued', 'wa']);
    }

    public function takeOver(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);
        $this->wa->takeOver($conversation, $request->user());

        return redirect()->route('inbox', ['c' => $conversation->id])->with('toast', ['You are handling this chat — the bot is paused', 'ok']);
    }

    public function release(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);
        $this->wa->release($conversation);

        return redirect()->route('inbox', ['c' => $conversation->id])->with('toast', ['Chat handed back to the bot', 'ok']);
    }

    /** Demo: inject an inbound customer message and let the bot answer it. */
    public function simulate(Request $request, Conversation $conversation)
    {
        abort_unless(config('crm.demo_switcher'), 404);
        $this->authorizeConversation($request, $conversation);

        $text = $request->input('body', 'Hi') ?: 'Hi';
        $this->wa->receive($conversation, $text);
        $result = $this->bot->handle($conversation->fresh('customer'), $text);

        $redirect = redirect()->route('inbox', ['c' => $conversation->id]);
        if ($result['booking']) {
            $redirect->with('toast', ['Booking '.$result['booking']->ref.' + lead created from WhatsApp', 'wa']);
        }

        return $redirect;
    }

    /** Technicians only see chats with customers on their jobs; managers their branch. */
    protected function visible(Request $request)
    {
        $u = $request->user();

        return Conversation::query()
            ->when($u->role === 'tech', fn ($q) => $q->whereHas('customer.bookings', fn ($b) => $b->where('technician_id', $u->technician_id)))
            ->when($u->isBranchScoped(), fn ($q) => $q->whereHas('customer', fn ($c) => $c->where('branch_id', $u->branch_id)));
    }

    protected function authorizeConversation(Request $request, Conversation $c): void
    {
        abort_unless($this->visible($request)->whereKey($c->id)->exists(), 403);
    }
}
