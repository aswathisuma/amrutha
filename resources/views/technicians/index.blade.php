@extends('layouts.app')
@section('title', 'Technicians')

@section('content')
<x-page-head title="Technicians" :sub="$techs->count().' field staff'">
    <x-btn :data-panel-url="route('technicians.create')">+ Add Technician</x-btn>
</x-page-head>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
    @forelse ($techs as $t)
        <div class="bg-white rounded-2xl border border-slate2 p-4">
            <div class="flex items-center gap-3"><x-avatar :name="$t->name" :size="42" />
                <div class="flex-1"><div class="font-semibold text-ink text-[14px]">{{ $t->name }}</div><div class="text-[11.5px] text-slate-500">{{ $t->skill }}</div></div>
                <x-badge :color="$t->statusColor()">{{ $t->status }}</x-badge>
            </div>
            <div class="grid grid-cols-3 gap-2 mt-3 text-center">
                <div><div class="num text-[15px] font-bold">{{ $t->jobs_done }}</div><div class="text-[10px] text-slate-400">Jobs</div></div>
                <div><div class="num text-[15px] font-bold text-amber">{{ number_format($t->rating, 1) }}★</div><div class="text-[10px] text-slate-400">Rating</div></div>
                <div><div class="num text-[15px] font-bold">{{ $t->load }}</div><div class="text-[10px] text-slate-400">Queue</div></div>
            </div>
            <div class="text-[11.5px] text-slate-400 mt-3 flex items-center justify-between"><span>{{ $t->branch?->name ?? '—' }}</span><span>{{ $t->phone }}</span></div>
        </div>
    @empty
        <x-empty title="No technicians" sub="Add your first field technician." />
    @endforelse
</div>
@endsection
