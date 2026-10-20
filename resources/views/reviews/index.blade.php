@extends('layouts.app')
@section('title', 'Reviews')

@section('content')
<x-page-head title="Reviews & Ratings" sub="Feedback collected via WhatsApp" />

<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <x-kpi label="Average" :value="$avg.'★'" :sub="$reviews->count().' total'" rule="bg-amber" icon="star" />
    <x-kpi label="5-star" :value="$five" :sub="$fivePct.'%'" rule="bg-brand" icon="star" />
    <x-kpi label="Needs attention" :value="$negative" sub="rated ≤ 3" rule="bg-rose-500" icon="life" />
    <x-kpi label="Response rate" :value="$responseRate.'%'" sub="of completed jobs" rule="bg-blue-500" icon="chat" />
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-3">
    @forelse ($reviews as $r)
        <div class="bg-white rounded-2xl border border-slate2 p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5"><x-avatar :name="$r->customer->name" :size="34" />
                    <div><div class="text-[13px] font-medium text-ink">{{ $r->customer->name }}</div><div class="text-[11px] text-slate-400">{{ $r->service_name }} · {{ $r->technician?->name ?? '—' }}</div></div>
                </div>
                <x-stars :n="$r->rating" />
            </div>
            <p class="text-[13px] text-slate-600 mt-2.5">“{{ $r->comment }}”</p>
            <div class="text-[11px] text-slate-400 mt-2">{{ $r->created_at->format('d M') }}</div>
        </div>
    @empty
        <x-empty title="No reviews yet" sub="Feedback requests go out 24h after a job is paid." />
    @endforelse
</div>
@endsection
