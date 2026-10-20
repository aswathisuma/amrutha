<x-drawer>
    <x-slot:header>
        <div class="font-semibold text-ink">Activity timeline</div>
        <div class="text-[12px] text-slate-500">Latest 100 events across the CRM</div>
    </x-slot:header>

    @forelse ($days as $day => $items)
        <div class="bg-white rounded-2xl border border-slate2 p-4">
            <div class="text-[12px] font-semibold mb-3">{{ $day === local_now()->toDateString() ? 'Today' : \Illuminate\Support\Carbon::parse($day)->format('d M Y') }}</div>
            <div class="space-y-3">
                @foreach ($items as $a)
                    <div class="flex gap-2.5 text-[12.5px]">
                        <div class="w-1.5 h-1.5 rounded-full bg-brand-400 mt-1.5 shrink-0"></div>
                        <div class="flex-1 leading-tight"><span class="font-medium text-ink">{{ $a->subject }}</span> <span class="text-slate-500">{{ $a->action }}</span></div>
                        <div class="text-[10.5px] text-slate-400 shrink-0">{{ local_time($a->created_at)->format('h:i A') }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <x-empty title="No activity yet" sub="Bookings, payments and chats will appear here." />
    @endforelse
</x-drawer>
