@extends('layouts.app')
@section('title', 'Leads')

@section('content')
<x-page-head title="Leads" sub="Pipeline of enquiries and prospects">
    <x-btn :data-panel-url="route('leads.create')">+ Add Lead</x-btn>
</x-page-head>

<div class="overflow-x-auto pb-2 -mx-1">
    <div class="flex gap-3 px-1 min-w-max">
        @foreach ($stages as $stage)
            @php($items = $leads->get($stage, collect()))
            <div class="w-[220px] shrink-0">
                <div class="flex items-center justify-between px-1 mb-2">
                    <span class="text-[12.5px] font-semibold text-ink">{{ $stage }}</span>
                    <span class="text-[11px] text-slate-400 num" title="{{ money($items->sum('value')) }} pipeline value">{{ $items->count() }}</span>
                </div>
                <div class="space-y-2 min-h-[60px]">
                    @forelse ($items as $l)
                        <div data-panel-url="{{ route('leads.show', $l) }}" class="bg-white rounded-xl border border-slate2 p-3 cursor-pointer hover:shadow-soft transition">
                            <div class="flex items-center gap-2"><x-avatar :name="$l->name" :size="26" /><span class="text-[12.5px] font-medium text-ink truncate">{{ $l->name }}</span></div>
                            <div class="text-[11.5px] text-slate-500 mt-1.5">{{ $l->service_name }}</div>
                            <div class="flex items-center justify-between mt-2">
                                <x-badge color="bg-brand-50 text-brand-600">{{ $l->source }}</x-badge>
                                <span class="num text-[12px] font-semibold text-ink">{{ money($l->value) }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="text-[11px] text-slate-300 px-1 py-3">Empty</div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
