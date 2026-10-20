<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\WaTemplate;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;

class ConversationController extends ApiController
{
    public function __construct(protected WhatsAppService $wa) {}

    /** GET /conversations?mode=&needs_attention= */
    public function index(Request $request)
    {
        $this->requireSection($request, 'inbox');
        $f = $request->validate(['mode' => ['nullable', 'in:bot,human'], 'needs_attention' => ['nullable', 'boolean']]);

        $q = $this->visible($request)->with(['customer', 'latestMessage'])
            ->when($f['mode'] ?? null, fn ($q, $v) => $q->where('mode', $v))
            ->when($request->has('needs_attention'), fn ($q) => $q->where('needs_attention', $request->boolean('needs_attention')));

        return $this->paginate($q, $request, ConversationResource::class, ['id', 'last_message_at'], '-last_message_at');
    }

    public function messages(Request $request, Conversation $conversation)
    {
        $this->requireSection($request, 'inbox');
        $this->authorizeConversation($request, $conversation);

        return $this->paginate($conversation->messages()->getQuery()->reorder(), $request, MessageResource::class, ['id', 'sent_at'], '-sent_at');
    }

    /** POST /conversations/{id}/messages { body } or { template } — queued for delivery. */
    public function send(Request $request, Conversation $conversation)
    {
        $this->requireSection($request, 'inbox');
        $this->authorizeConversation($request, $conversation);
        $data = $request->validate([
            'body' => ['required_without:template', 'nullable', 'string', 'max:4096'],
            'template' => ['required_without:body', 'nullable', 'string', 'exists:wa_templates,name'],
        ]);

        $msg = isset($data['template'])
            ? $this->wa->sendTemplate($conversation, WaTemplate::where('name', $data['template'])->firstOrFail(), $request->user())
            : $this->wa->sendAgentMessage($conversation, $data['body'], $request->user());

        return $this->created(new MessageResource($msg));
    }

    /** Technicians only see chats with customers on their own jobs; managers their branch. */
    protected function visible(Request $request)
    {
        $u = $request->user();

        return Conversation::query()
            ->when($u->role === 'tech', fn ($q) => $q->whereHas('customer.bookings', fn ($b) => $b->where('technician_id', $u->technician_id)))
            ->when($u->isBranchScoped(), fn ($q) => $q->whereHas('customer', fn ($c) => $c->where('branch_id', $u->branch_id)));
    }

    protected function authorizeConversation(Request $request, Conversation $c): void
    {
        abort_unless($this->visible($request)->whereKey($c->id)->exists(), 403, 'This conversation is outside your scope.');
    }
}
