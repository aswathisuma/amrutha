@php($me = auth()->user())
<x-drawer>
    <x-slot:header>
        <div class="flex items-center gap-3">
            <x-avatar :name="$c->name" :size="44" />
            <div><div class="font-semibold text-ink">{{ $c->name }}</div><div class="text-[12px] text-slate-500">{{ $c->email ?: 'No email' }}</div></div>
        </div>
    </x-slot:header>

    <div class="grid grid-cols-3 gap-2">
        <div class="bg-white rounded-xl border border-slate2 p-3"><div class="num text-[18px] font-bold">{{ $c->bookings->count() }}</div><div class="text-[11px] text-slate-400">Bookings</div></div>
        <div class="bg-white rounded-xl border border-slate2 p-3"><div class="num text-[18px] font-bold">{{ $c->completed_count }}</div><div class="text-[11px] text-slate-400">Completed</div></div>
        <div class="bg-white rounded-xl border border-slate2 p-3"><div class="num text-[18px] font-bold text-brand-600">{{ money($c->spend) }}</div><div class="text-[11px] text-slate-400">Spend</div></div>
    </div>

    <div class="bg-white rounded-2xl border border-slate2 p-4 grid grid-cols-2 gap-y-2">
        <div><div class="text-[11px] text-slate-400">Phone</div>{{ $c->phone }}</div>
        <div><div class="text-[11px] text-slate-400">WhatsApp</div>{!! $c->whatsapp ? '<span class="text-brand-600">Connected</span>' : '<span class="text-slate-400">Not opted in</span>' !!}</div>
        <div><div class="text-[11px] text-slate-400">Area</div>{{ $c->area ?? '—' }}</div>
        <div><div class="text-[11px] text-slate-400">Branch</div>{{ $c->branch?->name ?? '—' }}</div>
    </div>

    <div class="bg-white rounded-2xl border border-slate2 p-4">
        <div class="text-[12px] font-semibold mb-3">Customer timeline</div>
        <div class="space-y-3">
            @forelse ($timeline as $ev)
                <div class="flex gap-2.5">
                    <div class="w-1.5 h-1.5 rounded-full bg-brand-400 mt-1.5 shrink-0"></div>
                    <div class="flex-1 text-[12px] text-ink">{{ $ev['text'] }}</div>
                    <div class="text-[10.5px] text-slate-400 shrink-0">{{ \Illuminate\Support\Carbon::parse($ev['at'])->format('d M') }}</div>
                </div>
            @empty
                <div class="text-[12px] text-slate-400">No touchpoints yet.</div>
            @endforelse
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate2 p-4">
        <div class="text-[12px] font-semibold mb-2">Recent bookings</div>
        @forelse ($c->bookings->take(4) as $b)
            <div class="flex items-center justify-between gap-2 py-1.5 text-[12.5px] border-b border-slate2 last:border-0">
                @if ($me->canSee('bookings'))
                    <button type="button" data-panel-url="{{ route('bookings.show', $b) }}" class="num text-brand-600 hover:underline">{{ $b->ref }}</button>
                @else
                    <span class="num text-brand-600">{{ $b->ref }}</span>
                @endif
                <span class="text-slate-500 truncate">{{ $b->service_name }}</span>
                <x-badge :status="$b->status" />
            </div>
        @empty
            <div class="text-slate-400 text-[12px]">No bookings yet</div>
        @endforelse
    </div>

    <div class="flex flex-wrap gap-2">
        @if ($me->canSee('inbox'))
            <x-btn :href="route('inbox.customer', $c)">Open WhatsApp chat</x-btn>
        @endif
        @if ($me->canSee('bookings'))
            <x-btn kind="ghost" :data-panel-url="route('bookings.create', ['customer' => $c->id])">+ New booking</x-btn>
        @endif
    </div>
</x-drawer>
