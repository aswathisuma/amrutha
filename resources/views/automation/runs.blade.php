<x-drawer>
    <x-slot:header>
        <div class="font-semibold text-ink">{{ $rule->action }}</div>
        <div class="text-[12px] text-slate-500">{{ $rule->trigger }} · last 100 runs</div>
    </x-slot:header>

    <div class="bg-white rounded-2xl border border-slate2 overflow-hidden">
        @forelse ($runs as $r)
            <div class="flex items-start gap-3 px-4 py-3 border-b border-slate2 last:border-0 text-[12.5px]">
                <x-badge :color="['sent' => 'bg-brand-50 text-brand-600', 'skipped' => 'bg-amber-50 text-amber', 'failed' => 'bg-rose-50 text-rose-600'][$r->status] ?? 'bg-slate-100 text-slate-500'">{{ $r->status }}</x-badge>
                <div class="flex-1 min-w-0">
                    <div class="text-ink">{{ $r->subject_type }} #{{ $r->subject_id }}</div>
                    @if ($r->detail)<div class="text-[11.5px] text-slate-500">{{ $r->detail }}</div>@endif
                    <div class="text-[10.5px] text-slate-400 num truncate" title="{{ $r->dedupe_key }}">{{ $r->dedupe_key }}</div>
                </div>
                <div class="text-[10.5px] text-slate-400 shrink-0">{{ local_time($r->created_at)->format('d M, h:i A') }}</div>
            </div>
        @empty
            <x-empty title="No runs yet" sub="This rule hasn't fired since the log was enabled." />
        @endforelse
    </div>
</x-drawer>
