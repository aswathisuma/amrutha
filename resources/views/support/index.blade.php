@extends('layouts.app')
@section('title', 'Support')

@section('content')
<x-page-head title="Support Tickets" :sub="$tickets->count().' complaints & requests'">
    <x-btn :data-panel-url="route('support.create')">+ New Ticket</x-btn>
</x-page-head>

<div class="bg-white rounded-2xl border border-slate2 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-[13px]">
            <thead class="bg-paper text-slate-500 text-[11.5px]">
            <tr>
                <th class="text-left font-medium px-4 py-2.5">Ticket</th>
                <th class="text-left font-medium px-4 py-2.5">Customer</th>
                <th class="text-left font-medium px-4 py-2.5 hidden md:table-cell">Issue</th>
                <th class="text-left font-medium px-4 py-2.5">Priority</th>
                <th class="text-left font-medium px-4 py-2.5 hidden sm:table-cell">Assigned</th>
                <th class="text-left font-medium px-4 py-2.5">Status</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($tickets as $t)
                <tr class="border-t border-slate2 hover:bg-paper">
                    <td class="px-4 py-3 num font-semibold text-brand-600">{{ $t->number }}</td>
                    <td class="px-4 py-3 font-medium">{{ $t->customer->name }}</td>
                    <td class="px-4 py-3 hidden md:table-cell text-slate-600">{{ $t->issue }}</td>
                    <td class="px-4 py-3"><x-badge :color="$t->priorityColor()">{{ $t->priority }}</x-badge></td>
                    <td class="px-4 py-3 hidden sm:table-cell text-slate-600">{{ $t->assigned_to ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <form method="POST" action="{{ route('support.update', $t) }}">
                            @csrf @method('PATCH')
                            <select name="status" onchange="this.form.submit()" class="text-[11px] font-medium rounded-full px-2 py-0.5 border-0 cursor-pointer {{ $t->statusColor() }}">
                                @foreach ($statuses as $s)<option value="{{ $s }}" class="bg-white text-ink" @selected($s === $t->status)>{{ $s }}</option>@endforeach
                            </select>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty title="No tickets" sub="Complaints and requests will show up here." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
