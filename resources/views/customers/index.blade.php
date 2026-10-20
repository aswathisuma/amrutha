@extends('layouts.app')
@section('title', 'Customers')

@section('content')
<x-page-head title="Customers" :sub="$customers->count().' in your CRM'">
    <x-btn :data-panel-url="route('customers.create')">+ Add Customer</x-btn>
</x-page-head>

<div class="bg-white rounded-2xl border border-slate2 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-[13px]">
            <thead class="bg-paper text-slate-500 text-[11.5px]">
            <tr>
                <th class="text-left font-medium px-4 py-2.5">Customer</th>
                <th class="text-left font-medium px-4 py-2.5 hidden sm:table-cell">Area</th>
                <th class="text-left font-medium px-4 py-2.5 hidden md:table-cell">Type</th>
                <th class="text-center font-medium px-4 py-2.5">Bookings</th>
                <th class="text-right font-medium px-4 py-2.5">Total spend</th>
                <th class="text-left font-medium px-4 py-2.5 hidden lg:table-cell">Tags</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($customers as $c)
                <tr class="border-t border-slate2 hover:bg-paper cursor-pointer" data-panel-url="{{ route('customers.show', $c) }}">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2.5"><x-avatar :name="$c->name" :size="32" />
                            <div class="leading-tight"><div class="font-medium text-ink">{{ $c->name }}</div><div class="text-[11px] text-slate-400">{{ $c->phone }}</div></div>
                        </div>
                    </td>
                    <td class="px-4 py-3 hidden sm:table-cell text-slate-600">{{ $c->area }}</td>
                    <td class="px-4 py-3 hidden md:table-cell"><x-badge :color="$c->type === 'Commercial' ? 'bg-violet-50 text-violet-700' : 'bg-slate-100 text-slate-600'">{{ $c->type }}</x-badge></td>
                    <td class="px-4 py-3 text-center num">{{ $c->bookings->count() }}</td>
                    <td class="px-4 py-3 text-right num font-semibold">{{ money($c->spend) }}</td>
                    <td class="px-4 py-3 hidden lg:table-cell">
                        @forelse ($c->tags ?? [] as $tag)
                            <x-badge color="bg-brand-50 text-brand-600">{{ $tag }}</x-badge>
                        @empty — @endforelse
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty title="No customers yet" sub="Add one or let WhatsApp enquiries create them." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
