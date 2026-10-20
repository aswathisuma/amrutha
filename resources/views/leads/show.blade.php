<x-drawer>
    <x-slot:header>
        <div class="flex items-center gap-3">
            <x-avatar :name="$l->name" :size="40" />
            <div><div class="font-semibold text-ink">{{ $l->name }}</div><div class="text-[12px] text-slate-500">{{ $l->phone }}</div></div>
        </div>
    </x-slot:header>

    <div class="bg-white rounded-2xl border border-slate2 p-4 grid grid-cols-2 gap-y-3">
        <div><div class="text-[11px] text-slate-400">Service</div>{{ $l->service_name }}</div>
        <div><div class="text-[11px] text-slate-400">Source</div><x-badge color="bg-brand-50 text-brand-600">{{ $l->source }}</x-badge></div>
        <div><div class="text-[11px] text-slate-400">Est. value</div><span class="num font-semibold">{{ money($l->value) }}</span></div>
        <div><div class="text-[11px] text-slate-400">Area</div>{{ $l->area ?? '—' }}</div>
        <div class="col-span-2"><div class="text-[11px] text-slate-400">Note</div>{{ $l->note ?? '—' }}</div>
        @if ($l->booking)
            <div class="col-span-2"><div class="text-[11px] text-slate-400">Booking</div><span class="num text-brand-600">{{ $l->booking->ref }}</span> · {{ $l->booking->status }}</div>
        @endif
    </div>

    <div class="bg-white rounded-2xl border border-slate2 p-4">
        <div class="text-[12px] font-semibold mb-2">Stage</div>
        <form method="POST" action="{{ route('leads.stage', $l) }}" class="flex flex-wrap gap-1.5">
            @csrf @method('PATCH')
            @foreach ($stages as $i => $s)
                <button type="submit" name="stage" value="{{ $s }}"
                        class="px-2.5 py-1.5 rounded-lg text-[11.5px] {{ $s === $l->stage ? 'bg-ink text-white' : ($i < $stageIdx ? 'bg-brand-50 text-brand-600' : 'bg-paper text-slate-500 hover:text-ink') }}">{{ $s }}</button>
            @endforeach
        </form>
    </div>

    <div class="flex gap-2">
        @unless ($l->booking_id)
            <form method="POST" action="{{ route('leads.convert', $l) }}">@csrf<x-btn type="submit">Convert to booking</x-btn></form>
        @endunless
        @if (auth()->user()->canSee('inbox'))
            <x-btn kind="ghost" :href="route('inbox.lead', $l)">Open chat</x-btn>
        @endif
    </div>
</x-drawer>
