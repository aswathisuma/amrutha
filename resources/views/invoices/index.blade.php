@extends('layouts.app')
@section('title', 'Invoices')

@section('content')
<x-page-head title="Invoices" :sub="$invoices->count().' generated'" />

<div class="bg-white rounded-2xl border border-slate2 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-[13px]">
            <thead class="bg-paper text-slate-500 text-[11.5px]">
            <tr>
                <th class="text-left font-medium px-4 py-2.5">Invoice</th>
                <th class="text-left font-medium px-4 py-2.5">Customer</th>
                <th class="text-left font-medium px-4 py-2.5 hidden md:table-cell">Service</th>
                <th class="text-left font-medium px-4 py-2.5 hidden sm:table-cell">Date</th>
                <th class="text-left font-medium px-4 py-2.5">Status</th>
                <th class="text-right font-medium px-4 py-2.5">Total</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($invoices as $iv)
                <tr class="border-t border-slate2 hover:bg-paper cursor-pointer" data-panel-url="{{ route('invoices.show', $iv) }}">
                    <td class="px-4 py-3 num font-semibold text-brand-600">{{ $iv->number }}</td>
                    <td class="px-4 py-3"><div class="flex items-center gap-2"><x-avatar :name="$iv->customer->name" :size="26" /><span class="font-medium text-ink">{{ $iv->customer->name }}</span></div></td>
                    <td class="px-4 py-3 hidden md:table-cell text-slate-600">{{ $iv->service_name }}</td>
                    <td class="px-4 py-3 hidden sm:table-cell text-slate-500">{{ $iv->issued_at?->format('d M') }}</td>
                    <td class="px-4 py-3"><x-badge :color="$iv->statusColor()">{{ $iv->status }}</x-badge></td>
                    <td class="px-4 py-3 text-right num font-semibold">{{ money($iv->total) }}</td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty title="No invoices yet" sub="Invoices are generated when a booking reaches “Invoice Generated”." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
