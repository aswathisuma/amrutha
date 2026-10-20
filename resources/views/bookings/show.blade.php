@php($c = $b->customer)
@php($me = auth()->user())
<x-drawer>
    <x-slot:header>
        <div class="num text-[17px] font-bold text-brand-600">{{ $b->ref }}</div>
        <div class="text-[12px] text-slate-500">{{ $b->service_name }} · {{ $b->area }}</div>
    </x-slot:header>

    <div class="bg-white rounded-2xl border border-slate2 p-4">
        <div class="flex items-center gap-3">
            <x-avatar :name="$c->name" :size="44" />
            <div class="flex-1"><div class="font-semibold text-ink">{{ $c->name }}</div><div class="text-[12px] text-slate-500">{{ $c->phone }}</div></div>
            @if ($me->canSee('inbox'))
                <a href="{{ route('inbox.customer', $c) }}" class="p-2 rounded-lg bg-brand-50 text-brand-600" title="Open WhatsApp">{!! crm_icon('chat', 'w-4 h-4') !!}</a>
            @endif
        </div>
        <div class="grid grid-cols-2 gap-y-2 gap-x-4 mt-3 text-[12.5px]">
            <div><div class="text-slate-400 text-[11px]">Date & time</div>{{ $b->scheduled_on->format('d M Y') }} · {{ $b->time_slot }}</div>
            <div><div class="text-slate-400 text-[11px]">Branch</div>{{ $b->branch?->name ?? '—' }}</div>
            <div><div class="text-slate-400 text-[11px]">Technician</div>{!! $b->technician ? e($b->technician->name) : '<span class="text-amber">Unassigned</span>' !!}</div>
            <div><div class="text-slate-400 text-[11px]">Source</div>{{ $b->source ?? '—' }}</div>
            <div class="col-span-2"><div class="text-slate-400 text-[11px]">Problem</div>{{ $b->description }}</div>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate2 p-4">
        <div class="text-[12.5px] font-semibold mb-3">Status timeline</div>
        @if ($idx < 0)
            <div class="mb-3"><x-badge :status="$b->status" /></div>
        @endif
        <div>
            @foreach ($statuses as $i => $s)
                @php($done = $idx >= 0 && $i <= $idx)
                <div class="flex gap-3 items-start">
                    <div class="flex flex-col items-center">
                        <div class="w-3.5 h-3.5 rounded-full {{ $done ? 'bg-brand' : 'bg-slate-200' }} grid place-items-center">@if ($done)<span class="text-white text-[8px]">✓</span>@endif</div>
                        @if (! $loop->last)<div class="w-px h-5 {{ $done ? 'bg-brand-400' : 'bg-slate-200' }}"></div>@endif
                    </div>
                    <div class="text-[12px] {{ $done ? 'text-ink font-medium' : 'text-slate-400' }} -mt-0.5 pb-1">{{ $s }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-3 gap-2 text-[13px]">
        <div class="bg-white rounded-xl border border-slate2 p-3"><div class="text-[11px] text-slate-400">Estimated</div><div class="num font-semibold">{{ money($b->est_amount) }}</div></div>
        <div class="bg-white rounded-xl border border-slate2 p-3"><div class="text-[11px] text-slate-400">Final</div><div class="num font-semibold">{{ $b->final_amount ? money($b->final_amount) : '—' }}</div></div>
        <div class="bg-white rounded-xl border border-slate2 p-3"><div class="text-[11px] text-slate-400">Payment</div>
            <x-badge :color="$b->payment_status === 'Paid' ? 'bg-brand-50 text-brand-600' : 'bg-orange-50 text-orange-700'">{{ $b->payment_status === '—' ? 'Pending' : $b->payment_status }}</x-badge>
        </div>
    </div>

    <div class="flex flex-wrap gap-2">
        @if ($next)
            <form method="POST" action="{{ route('bookings.advance', $b) }}">@csrf<x-btn type="submit">Advance → {{ $next }}</x-btn></form>
        @endif
        @if (! $b->technician_id && $idx >= 0)
            <x-btn kind="ghost" :data-panel-url="route('bookings.assign-form', $b)">Assign technician</x-btn>
        @endif
        @if ($me->canSee('inbox'))
            <x-btn kind="ghost" :href="route('inbox.customer', $c)">Send WhatsApp</x-btn>
        @endif
        @if ($b->invoice && $me->canSee('invoices'))
            <x-btn kind="ghost" :data-panel-url="route('invoices.show', $b->invoice)">View invoice</x-btn>
        @endif
        @if (! in_array($b->status, ['Cancelled', 'Paid', 'Closed'], true))
            <form method="POST" action="{{ route('bookings.cancel', $b) }}" data-confirm="Cancel booking {{ $b->ref }}?">@csrf<x-btn type="submit" kind="danger">Cancel</x-btn></form>
        @endif
    </div>
    <p class="text-[11px] text-slate-400">Advancing status auto-sends the matching WhatsApp message (unless its automation rule is off), generates the invoice when due, and logs the activity.</p>
</x-drawer>
