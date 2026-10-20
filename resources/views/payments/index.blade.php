@extends('layouts.app')
@section('title', 'Payments')

@section('content')
<x-page-head title="Payments" sub="Collections and outstanding" />

<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <x-kpi label="Collected" :value="money($paid->sum('total'))" :sub="$paid->count().' invoices'" rule="bg-brand" icon="card" />
    <x-kpi label="Outstanding" :value="money($due->sum('total'))" :sub="$due->count().' pending'" rule="bg-amber-500" icon="card" />
    <x-kpi label="This month" :value="money($thisMonth)" sub="paid since the 1st" rule="bg-blue-500" icon="chart" />
    <x-kpi label="Success rate" :value="($invoices->count() ? round($paid->count() / $invoices->count() * 100) : 0).'%'" sub="of invoices collected" rule="bg-teal-500" icon="bolt" />
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
                    <td class="px-4 py-3 num font-semibold text-brand-600">{{ $iv->number }}</td>
                    <td class="px-4 py-3 font-medium">{{ $iv->customer->name }}</td>
                    <td class="px-4 py-3 hidden sm:table-cell text-slate-600">{{ $iv->method ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <x-badge :color="$iv->statusColor()">{{ $iv->status }}</x-badge>
                        @unless ($iv->isPaid())
                            <form method="POST" action="{{ route('payments.paid', $iv) }}" class="inline">
                                @csrf
                                <input type="hidden" name="method" value="UPI">
                                <button class="text-[11px] text-brand-600 ml-1">Mark paid</button>
                            </form>
                        @endunless
                    </td>
                    <td class="px-4 py-3 text-right num font-semibold">{{ money($iv->total) }}</td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty title="No payments yet" sub="Collections will appear once invoices are issued." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
