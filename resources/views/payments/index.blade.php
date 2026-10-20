@extends('layouts.app')
@section('title', 'Payments')

@section('content')
<x-page-head title="Payments" sub="Collections and outstanding" />

<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <x-kpi label="Collected" :value="money($collected)" :sub="$collectedCount.' invoices paid'" rule="bg-brand" icon="card" />
    <x-kpi label="Outstanding" :value="money($outstanding)" :sub="$dueCount.' pending'" rule="bg-amber-500" icon="card" />
    <x-kpi label="This month" :value="money($thisMonth)" sub="net of refunds" rule="bg-blue-500" icon="chart" />
    <x-kpi label="Success rate" :value="$successRate.'%'" sub="of invoices collected" rule="bg-teal-500" icon="bolt" />
</div>

<div class="bg-white rounded-2xl border border-slate2 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-[13px]">
            <thead class="bg-paper text-slate-500 text-[11.5px]">
            <tr>
                <th class="text-left font-medium px-4 py-2.5">Invoice</th>
                <th class="text-left font-medium px-4 py-2.5">Customer</th>
                <th class="text-left font-medium px-4 py-2.5 hidden sm:table-cell">Method</th>
                <th class="text-left font-medium px-4 py-2.5">Status</th>
                <th class="text-right font-medium px-4 py-2.5">Amount</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($invoices as $iv)
                <tr class="border-t border-slate2 hover:bg-paper">
                    <td class="px-4 py-3 num font-semibold text-brand-600"><button type="button" data-panel-url="{{ route('invoices.show', $iv) }}" class="hover:underline">{{ $iv->number }}</button></td>
                    <td class="px-4 py-3 font-medium">{{ $iv->customer->name }}</td>
                    <td class="px-4 py-3 hidden sm:table-cell text-slate-600">{{ $iv->method ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <x-badge :color="$iv->statusColor()">{{ $iv->status }}</x-badge>
                        @if (in_array($iv->status, ['Pending', 'Partial'], true))
                            <form method="POST" action="{{ route('payments.paid', $iv) }}" class="inline" data-confirm="Record {{ money($iv->balance()) }} received by UPI for {{ $iv->number }}?">
                                @csrf
                                <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                                <input type="hidden" name="method" value="UPI">
                                <button class="text-[11px] text-brand-600 ml-1">Mark paid</button>
                            </form>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right num font-semibold">
                        {{ money($iv->total) }}
                        @if ($iv->status === 'Partial')<div class="text-[10.5px] text-amber font-normal">{{ money($iv->balance()) }} due</div>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty title="No payments yet" sub="Collections will appear once invoices are issued." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.pagination', ['p' => $invoices])
</div>
@endsection
