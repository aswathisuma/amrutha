<x-modal title="Assign technician" :sub="'Suggested by branch, workload and rating for '.$b->ref.'.'">
    <form method="POST" action="{{ route('bookings.assign', $b) }}" class="space-y-2">
        @csrf
        @forelse ($techs as $i => $t)
            <button type="submit" name="technician_id" value="{{ $t->id }}"
                    class="w-full flex items-center gap-3 p-3 rounded-xl border {{ $i === 0 ? 'border-brand-400 bg-brand-50' : 'border-slate2 hover:bg-paper' }}">
                <x-avatar :name="$t->name" :size="36" />
                <div class="flex-1 text-left">
                    <div class="text-[13px] font-medium">{{ $t->name }}</div>
                    <div class="text-[11.5px] text-slate-500">{{ $t->skill }} · {{ number_format($t->rating, 1) }}★ · queue {{ $t->load }}</div>
                </div>
                @if ($i === 0)<span class="text-[10px] text-brand-600 font-semibold">BEST MATCH</span>@endif
            </button>
        @empty
            <x-empty title="No technicians available" sub="Everyone is off duty right now." />
        @endforelse
    </form>
</x-modal>
