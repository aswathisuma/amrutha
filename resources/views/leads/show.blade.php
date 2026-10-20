<x-drawer>
    <x-slot:header>
        <div class="flex items-center gap-3">
            <x-avatar :name="$l->name" :size="40" />
            <div><div class="font-semibold text-ink">{{ $l->name }}</div><div class="text-[12px] text-slate-500">{{ $l->phone ?? 'No phone' }}</div></div>
        </div>
    </x-slot:header>

    <div class="bg-white rounded-2xl border border-slate2 p-4 grid grid-cols-2 gap-y-3">
        <div><div class="text-[11px] text-slate-400">Service</div>{{ $l->service_name }}</div>
        <div><div class="text-[11px] text-slate-400">Source</div><x-badge color="bg-brand-50 text-brand-600">{{ $l->source }}</x-badge></div>
        <div><div class="text-[11px] text-slate-400">Est. value</div><span class="num font-semibold">{{ money($l->value) }}</span></div>
        <div><div class="text-[11px] text-slate-400">Area / branch</div>{{ $l->area ?? '—' }}{{ $l->branch ? ' · '.$l->branch->name : '' }}</div>
        <div><div class="text-[11px] text-slate-400">Customer record</div>
            @if ($l->customer)
                @if (auth()->user()->canSee('customers'))
                    <button type="button" data-panel-url="{{ route('customers.show', $l->customer) }}" class="text-brand-600 hover:underline">{{ $l->customer->name }}</button>
                @else {{ $l->customer->name }} @endif
            @else <span class="text-slate-400">Not linked yet</span> @endif
        </div>
        <div><div class="text-[11px] text-slate-400">Created</div>{{ local_time($l->created_at)->format('d M Y') }}</div>
        <div class="col-span-2"><div class="text-[11px] text-slate-400">Note</div>{{ $l->note ?? '—' }}</div>
        @if ($l->booking)
            <div class="col-span-2"><div class="text-[11px] text-slate-400">Booking</div><span class="num text-brand-600">{{ $l->booking->ref }}</span> · {{ $l->booking->status }}</div>
        @endif
        @if ($l->stage === 'Lost')
            <div class="col-span-2"><div class="text-[11px] text-slate-400">Lost reason</div><span class="text-rose-600">{{ $l->lost_reason }}</span></div>
        @endif
    </div>

    <div class="bg-white rounded-2xl border border-slate2 p-4 space-y-3">
        <div>
            <div class="text-[12px] font-semibold mb-2">Stage</div>
            <form method="POST" action="{{ route('leads.stage', $l) }}" class="flex flex-wrap gap-1.5">
                @csrf @method('PATCH')
                @foreach ($stages as $i => $s)
                    @continue(in_array($s, ['Converted', 'Lost'], true) && $s !== $l->stage)
                    <button type="submit" name="stage" value="{{ $s }}"
                            class="px-2.5 py-1.5 rounded-lg text-[11.5px] {{ $s === $l->stage ? 'bg-ink text-white' : ($i < $stageIdx ? 'bg-brand-50 text-brand-600' : 'bg-paper text-slate-500 hover:text-ink') }}">{{ $s }}</button>
                @endforeach
            </form>
        </div>

        <form method="POST" action="{{ route('leads.stage', $l) }}" class="flex items-end gap-2 text-[12.5px]">
            @csrf @method('PATCH')
            <input type="hidden" name="stage" value="{{ $l->stage }}">
            <div class="flex-1"><x-field label="Owner">
                <select name="owner_user_id" class="input">
                    <option value="">Unassigned</option>
                    @foreach ($owners as $o)<option value="{{ $o->id }}" @selected($l->owner_user_id === $o->id)>{{ $o->name }}</option>@endforeach
                </select>
            </x-field></div>
            <x-btn type="submit" kind="ghost">Save</x-btn>
        </form>

        @unless (in_array($l->stage, ['Converted', 'Lost'], true))
            <details>
                <summary class="cursor-pointer text-[12px] text-rose-600">Mark as lost…</summary>
                <form method="POST" action="{{ route('leads.stage', $l) }}" class="flex items-end gap-2 mt-2 text-[12.5px]">
                    @csrf @method('PATCH')
                    <input type="hidden" name="stage" value="Lost">
                    <div class="flex-1"><x-field label="Why was it lost? (required)"><input name="lost_reason" required maxlength="255" class="input" placeholder="e.g. Price too high, went with competitor"></x-field></div>
                    <x-btn type="submit" kind="danger">Mark lost</x-btn>
                </form>
            </details>
        @endunless
    </div>

    <div class="flex gap-2">
        @unless ($l->booking_id || $l->stage === 'Lost')
            <form method="POST" action="{{ route('leads.convert', $l) }}">@csrf<x-btn type="submit">Convert to booking</x-btn></form>
        @endunless
        @if (auth()->user()->canSee('inbox') && $l->phone)
            <x-btn kind="ghost" :href="route('inbox.lead', $l)">Open chat</x-btn>
        @endif
    </div>
</x-drawer>
