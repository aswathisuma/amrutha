@extends('layouts.app')
@section('title', 'WhatsApp Inbox')

@section('content')
<x-page-head title="WhatsApp Inbox" :sub="$unread.' unread · Meta Cloud API'">
    <span class="inline-flex items-center gap-1.5 text-[12px] text-brand-600 bg-brand-50 px-2.5 py-1.5 rounded-lg"><span class="w-2 h-2 bg-brand-500 rounded-full"></span>{{ config('crm.whatsapp.token') ? 'API Connected' : 'Local mode' }}</span>
</x-page-head>

<div class="bg-white rounded-2xl border border-slate2 overflow-hidden flex h-[calc(100vh-210px)] min-h-[440px]">
    {{-- conversation list --}}
    <div class="w-full md:w-[300px] border-r border-slate2 flex-col shrink-0 {{ $active ? 'hidden md:flex' : 'flex' }}">
        <form method="GET" action="{{ route('inbox') }}" class="p-3 border-b border-slate2">
            <input type="hidden" name="list" value="1">
            <input name="q" value="{{ request('q') }}" class="w-full bg-paper rounded-lg px-3 py-2 text-[12.5px] outline-none" placeholder="Search conversations">
        </form>
        <div class="flex-1 overflow-y-auto">
            @forelse ($conversations as $cv)
                @php($last = $cv->latestMessage)
                <a href="{{ route('inbox', ['c' => $cv->id]) }}" class="w-full text-left px-3 py-3 border-b border-slate2 hover:bg-paper flex gap-2.5 {{ $active?->id === $cv->id ? 'bg-brand-50' : '' }}">
                    <x-avatar :name="$cv->customer->name" :size="38" />
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between"><span class="text-[13px] font-medium text-ink truncate">{{ $cv->customer->name }}</span><span class="text-[10px] text-slate-400">{{ $cv->last_message_at ? ago($cv->last_message_at) : '' }}</span></div>
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[11.5px] text-slate-500 truncate">{{ $last ? ($last->direction === 'out' ? '↳ ' : '').$last->body : 'No messages yet' }}</span>
                            @if ($cv->unread && $active?->id !== $cv->id)
                                <span class="num text-[10px] bg-brand text-white rounded-full w-4 h-4 grid place-items-center shrink-0">{{ $cv->unread }}</span>
                            @endif
                        </div>
                    </div>
                </a>
            @empty
                <x-empty title="No conversations" sub="Inbound WhatsApp messages will appear here." />
            @endforelse
        </div>
    </div>

    {{-- chat pane --}}
    <div class="flex-1 flex-col min-w-0 {{ $active ? 'flex' : 'hidden md:flex' }}">
        @if ($active)
            @php($c = $active->customer)
            <div class="h-14 border-b border-slate2 flex items-center gap-2.5 px-4 shrink-0">
                <a href="{{ route('inbox', ['list' => 1]) }}" class="md:hidden p-1 text-slate-400 text-lg">‹</a>
                <x-avatar :name="$c->name" :size="34" />
                <div class="flex-1"><div class="text-[13.5px] font-medium text-ink">{{ $c->name }}</div><div class="text-[11px] text-slate-400">{{ $c->phone }}</div></div>
                @if (auth()->user()->canSee('customers'))
                    <button type="button" data-panel-url="{{ route('customers.show', $c) }}" class="text-[11.5px] text-brand-600 px-2.5 py-1.5 rounded-lg hover:bg-brand-50">Profile</button>
                @endif
            </div>

            <div id="msgArea" class="flex-1 overflow-y-auto p-4 space-y-2 bg-[#f0f4f2]">
                @forelse ($active->messages as $m)
                    <div class="flex {{ $m->direction === 'out' ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[76%] px-3 py-2 rounded-2xl text-[12.5px] leading-snug whitespace-pre-line {{ $m->direction === 'out' ? 'bg-brand text-white rounded-br-sm' : 'bg-white text-ink rounded-bl-sm border border-slate2' }}">{{ $m->body }}<div class="text-[9.5px] {{ $m->direction === 'out' ? 'text-white/60' : 'text-slate-400' }} mt-0.5 text-right">{{ $m->sent_at->format('h:i A') }}{{ $m->is_template ? ' · template' : '' }}</div></div>
                    </div>
                @empty
                    <div class="text-center text-[12px] text-slate-400 py-10">No messages yet. Send a template to start the conversation.</div>
                @endforelse
            </div>

            <div class="border-t border-slate2 p-3 shrink-0">
                <div class="flex items-center gap-2 mb-2">
                    <form method="POST" action="{{ route('inbox.simulate', $active) }}">
                        @csrf
                        <input type="hidden" name="body" value="Hi">
                        <button class="text-[11px] px-2.5 py-1 rounded-lg bg-brand-50 text-brand-600" title="Simulate the customer sending “Hi” and let the bot reply">▶ Simulate "Hi" (bot flow)</button>
                    </form>
                    <button type="button" data-panel-url="{{ route('inbox.templates', $active) }}" class="text-[11px] px-2.5 py-1 rounded-lg bg-paper text-slate-600 border border-slate2">Send template</button>
                </div>
                <form method="POST" action="{{ route('inbox.send', $active) }}" class="flex items-center gap-2">
                    @csrf
                    <input id="chatInput" name="body" required autocomplete="off" autofocus class="flex-1 bg-paper rounded-xl px-3.5 py-2.5 text-[13px] outline-none focus:ring-2 ring-brand-400" placeholder="Type a message…">
                    <button class="w-10 h-10 rounded-xl bg-brand text-white grid place-items-center shrink-0" aria-label="Send">➤</button>
                </form>
            </div>
        @else
            <div class="flex-1 grid place-items-center"><x-empty title="Select a conversation" sub="Pick a chat on the left to reply." /></div>
        @endif
    </div>
</div>
@endsection
