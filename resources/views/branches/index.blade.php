@extends('layouts.app')
@section('title', 'Branches')

@section('content')
<x-page-head title="Branches" sub="Franchise network across Kerala">
    <x-btn :data-panel-url="route('branches.create')">+ Add Branch</x-btn>
</x-page-head>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
    @foreach ($branches as $b)
        <div class="bg-white rounded-2xl border border-slate2 p-5">
            <div class="flex items-center gap-2.5 mb-3">
                <div class="w-9 h-9 rounded-xl bg-ink text-white grid place-items-center">{!! crm_icon('building', 'w-4 h-4') !!}</div>
                <div><div class="font-semibold text-ink text-[14px]">{{ $b->name }}</div><div class="text-[11.5px] text-slate-400">{{ $b->city }}, {{ $b->state }}</div></div>
            </div>
            <div class="grid grid-cols-2 gap-2 text-[12.5px]">
                <div class="bg-paper rounded-lg p-2.5"><div class="text-[10.5px] text-slate-400">Manager</div>{{ $b->manager_name ?? '—' }}</div>
                <div class="bg-paper rounded-lg p-2.5"><div class="text-[10.5px] text-slate-400">Bookings</div><span class="num font-semibold">{{ $b->bookings_count }}</span></div>
                <div class="bg-paper rounded-lg p-2.5"><div class="text-[10.5px] text-slate-400">Technicians</div><span class="num font-semibold">{{ $b->technicians_count }}</span></div>
                <div class="bg-paper rounded-lg p-2.5"><div class="text-[10.5px] text-slate-400">Revenue</div><span class="num font-semibold text-brand-600">{{ money($revenue[$b->id] ?? 0) }}</span></div>
            </div>
        </div>
    @endforeach
</div>
@endsection
