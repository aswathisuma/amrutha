<x-drawer>
    <x-slot:header>
        <div class="num text-[17px] font-bold text-brand-600">{{ $t->number }}</div>
        <div class="text-[12px] text-slate-500">{{ $t->issue }}</div>
    </x-slot:header>

    <div class="bg-white rounded-2xl border border-slate2 p-4 grid grid-cols-2 gap-y-3 text-[12.5px]">
        <div><div class="text-[11px] text-slate-400">Customer</div>{{ $t->customer->name }}<div class="text-[11px] text-slate-400">{{ $t->customer->phone }}</div></div>
        <div><div class="text-[11px] text-slate-400">Booking</div>
            @if ($t->booking && auth()->user()->canSee('bookings'))
                <button type="button" data-panel-url="{{ route('bookings.show', $t->booking) }}" class="num text-brand-600 hover:underline">{{ $t->booking->ref }}</button>
            @else {{ $t->booking?->ref ?? '—' }} @endif
        </div>
        <div><div class="text-[11px] text-slate-400">Priority</div><x-badge :color="$t->priorityColor()">{{ $t->priority }}</x-badge></div>
        <div><div class="text-[11px] text-slate-400">Source · assigned</div>{{ $t->source }} · {{ $t->assigned_to ?? 'Unassigned' }}</div>
        @if ($t->description)<div class="col-span-2"><div class="text-[11px] text-slate-400">Description</div><p class="whitespace-pre-line">{{ $t->description }}</p></div>@endif
    </div>

    <form method="POST" action="{{ route('support.update', $t) }}" class="bg-white rounded-2xl border border-slate2 p-4 space-y-2 text-[12.5px]">
        @csrf @method('PATCH')
        <input type="hidden" name="reopen" value="1">
        <div class="text-[12px] font-semibold">Status</div>
        <div class="flex gap-2">
            <select name="status" class="input flex-1">
                @foreach ($statuses as $s)<option @selected($s === $t->status)>{{ $s }}</option>@endforeach
            </select>
            <x-btn type="submit" kind="ghost">Update</x-btn>
        </div>
        <input name="note" class="input" placeholder="Resolution / note (required to close an unresolved ticket)">
    </form>

    <div class="bg-white rounded-2xl border border-slate2 p-4">
        <div class="text-[12px] font-semibold mb-3">History</div>
        <div class="space-y-3">
            @foreach ($t->events as $e)
                <div class="flex gap-2.5 text-[12.5px]">
                    <div class="w-1.5 h-1.5 rounded-full {{ $e->type === 'comment' ? 'bg-blue-500' : 'bg-brand-400' }} mt-1.5 shrink-0"></div>
                    <div class="flex-1 leading-snug">
                        <span class="font-medium text-ink">{{ $e->user?->name ?? 'System' }}</span>
                        <span class="text-slate-500">
                            @switch($e->type)
                                @case('created') opened the ticket @break
                                @case('status') changed status {{ $e->from_value }} → {{ $e->to_value }} @break
                                @default commented
                            @endswitch
                        </span>
                        @if ($e->body && $e->type !== 'created')<p class="text-slate-600 whitespace-pre-line mt-0.5">{{ $e->body }}</p>@endif
                        <div class="text-[10.5px] text-slate-400">{{ local_time($e->created_at)->format('d M, h:i A') }}</div>
                    </div>
                </div>
            @endforeach
        </div>
        <form method="POST" action="{{ route('support.comment', $t) }}" class="flex gap-2 mt-4">
            @csrf
            <input name="body" required class="input flex-1" placeholder="Add a comment…">
            <x-btn type="submit">Post</x-btn>
        </form>
    </div>
</x-drawer>
