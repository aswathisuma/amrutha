@extends('layouts.app')
@section('title', 'Bookings')

@section('content')
<x-page-head title="Bookings" :sub="$total.' total service jobs'">
    <x-btn :data-panel-url="route('bookings.create')">+ New Booking</x-btn>
</x-page-head>

<div class="flex gap-2 mb-4 overflow-x-auto pb-1">
    @foreach ($filters as $f)
        <a href="{{ route('bookings', $f === 'All' ? [] : ['status' => $f]) }}" class="px-3 py-1.5 rounded-lg text-[12.5px] whitespace-nowrap {{ $filter === $f ? 'bg-ink text-white' : 'bg-white border border-slate2 text-slate-600 hover:text-ink' }}">{{ $f }}</a>
    @endforeach
</div>

<div class="bg-white rounded-2xl border border-slate2 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-[13px]">
            <thead class="bg-paper text-slate-500 text-[11.5px]">
            <tr>
                <th class="text-left font-medium px-4 py-2.5">Booking</th>
                <th class="text-left font-medium px-4 py-2.5">Customer</th>
                <th class="text-left font-medium px-4 py-2.5 hidden md:table-cell">Service</th>
                <th class="text-left font-medium px-4 py-2.5 hidden lg:table-cell">Technician</th>
                <th class="text-left font-medium px-4 py-2.5 hidden sm:table-cell">Date</th>
                <th class="text-left font-medium px-4 py-2.5">Status</th>
                <th class="text-right font-medium px-4 py-2.5">Amount</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($bookings as $b)
                <tr class="border-t border-slate2 hover:bg-paper cursor-pointer" data-panel-url="{{ route('bookings.show', $b) }}">
                    <td class="px-4 py-3"><div class="num font-semibold text-brand-600">{{ $b->ref }}</div><div class="text-[11px] text-slate-400 sm:hidden">{{ $b->scheduled_on->format('d M') }}</div></td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2"><x-avatar :name="$b->customer->name" :size="28" />
                            <div class="leading-tight"><div class="font-medium text-ink">{{ $b->customer->name }}</div><div class="text-[11px] text-slate-400">{{ $b->area }}</div></div>
                        </div>
                    </td>
                    <td class="px-4 py-3 hidden md:table-cell text-slate-600">{{ $b->service_name }}</td>
                    <td class="px-4 py-3 hidden lg:table-cell text-slate-600">{!! $b->technician ? e($b->technician->name) : '<span class="text-slate-300">Unassigned</span>' !!}</td>
                    <td class="px-4 py-3 hidden sm:table-cell text-slate-500">{{ $b->scheduled_on->format('d M') }} · {{ $b->time_slot }}</td>
                    <td class="px-4 py-3"><x-badge :status="$b->status" /></td>
                    <td class="px-4 py-3 text-right num font-semibold">
                        @if ($b->final_amount) {{ money($b->final_amount) }} @else <span class="text-slate-400">{{ money($b->est_amount) }}</span> @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><x-empty title="No bookings here" sub="Try a different filter or create one." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.pagination', ['p' => $bookings])
</div>
@endsection
