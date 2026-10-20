@extends('layouts.app')
@section('title', 'My Jobs')

@section('content')
<x-page-head title="My Jobs" :sub="$t->name.' · '.$t->skill">
    @if ($allTechs->isNotEmpty())
        <form method="GET" action="{{ route('field') }}">
            <select name="tech" onchange="this.form.submit()" class="text-[12.5px] border border-slate2 rounded-lg px-2.5 py-1.5 bg-white" title="Preview a technician's job list">
                @foreach ($allTechs as $opt)<option value="{{ $opt->id }}" @selected($opt->id === $t->id)>{{ $opt->name }}</option>@endforeach
            </select>
        </form>
    @endif
    <x-badge :color="$t->statusColor()">{{ $t->status }}</x-badge>
</x-page-head>

<div class="grid grid-cols-3 gap-3 mb-4">
    <div class="bg-white rounded-2xl border border-slate2 p-4"><div class="num text-[22px] font-bold">{{ $active->count() }}</div><div class="text-[11.5px] text-slate-400">Active jobs</div></div>
    <div class="bg-white rounded-2xl border border-slate2 p-4"><div class="num text-[22px] font-bold">{{ $completed }}</div><div class="text-[11.5px] text-slate-400">Completed</div></div>
    <div class="bg-white rounded-2xl border border-slate2 p-4"><div class="num text-[22px] font-bold text-amber">{{ number_format($t->rating, 1) }}★</div><div class="text-[11.5px] text-slate-400">Rating</div></div>
</div>

<div class="space-y-3">
    @forelse ($active as $j)
        @php($c = $j->customer)
        @php($next = $j->nextStatus())
        <div class="bg-white rounded-2xl border border-slate2 p-4">
            <div class="flex items-start justify-between">
                <div><div class="num text-[14px] font-bold text-brand-600">{{ $j->ref }}</div><div class="text-[13px] font-medium text-ink mt-0.5">{{ $j->service_name }}</div></div>
                <x-badge :status="$j->status" />
            </div>
            <div class="mt-3 space-y-1.5 text-[12.5px] text-slate-600">
                <div class="flex items-center gap-2"><x-avatar :name="$c->name" :size="24" /> {{ $c->name }} · {{ $c->phone }}</div>
                <div>📍 {{ $j->area }} · {{ $j->scheduled_on->format('d M') }} {{ $j->time_slot }}</div>
                <div class="text-slate-500">🛠️ {{ $j->description }}</div>
            </div>
            <div class="flex flex-wrap gap-2 mt-3">
                <a href="tel:{{ $c->phone }}" class="px-3 py-2 rounded-xl bg-paper text-ink text-[12.5px] border border-slate2">Call</a>
                <a href="{{ route('inbox.customer', $c) }}" class="px-3 py-2 rounded-xl bg-brand-50 text-brand-600 text-[12.5px]">WhatsApp</a>
                <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($j->area.', Kerala') }}" target="_blank" rel="noopener" class="px-3 py-2 rounded-xl bg-paper text-ink text-[12.5px] border border-slate2">Navigate</a>
                <button type="button" data-panel-url="{{ route('bookings.work-form', $j) }}" class="px-3 py-2 rounded-xl bg-paper text-ink text-[12.5px] border border-slate2">Notes & photos{{ $j->attachments_count ? ' ('.$j->attachments_count.')' : '' }}</button>
                @if (in_array('No Show', $j->allowedTransitions(), true))
                    <button type="button" data-panel-url="{{ route('bookings.status-form', [$j, 'action' => 'No Show']) }}" class="px-3 py-2 rounded-xl bg-rose-50 text-rose-600 text-[12.5px]">No show</button>
                @endif
                @if ($next && in_array($next, config('crm.technician_statuses'), true))
                    <form method="POST" action="{{ route('bookings.advance', $j) }}" class="ml-auto">
                        @csrf
                        <button class="px-3 py-2 rounded-xl bg-brand text-white text-[12.5px]">{{ str_replace(['Technician ', 'Service '], '', $next) }}</button>
                    </form>
                @elseif ($j->status === 'Service Completed')
                    <span class="ml-auto px-3 py-2 text-[12px] text-slate-400">Waiting for office to finalise charges</span>
                @endif
            </div>
        </div>
    @empty
        <x-empty title="No active jobs" sub="You are all caught up. Nice work." />
    @endforelse
</div>
@endsection
