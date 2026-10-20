@php($to = $iv->billedTo())
<div class="flex items-start justify-between mb-5">
    <div class="flex items-center gap-2.5">
        <div class="w-9 h-9 rounded-xl bg-brand grid place-items-center text-white font-display font-bold">H</div>
        <div><div class="font-display font-bold text-ink">{{ config('crm.brand.name') }}</div><div class="text-[11px] text-slate-400">{{ config('crm.brand.tagline') }}</div></div>
    </div>
    <div class="text-right">
        <div class="num font-bold text-brand-600">{{ $iv->number }}</div>
        <div class="text-[11.5px] text-slate-400">{{ local_time($iv->issued_at)?->format('d M Y') }}</div>
        @if ($iv->due_date && ! $iv->isPaid())<div class="text-[11px] {{ $iv->due_date->isPast() ? 'text-rose-500' : 'text-slate-400' }}">Due {{ $iv->due_date->format('d M Y') }}</div>@endif
    </div>
</div>
<div class="grid grid-cols-2 gap-4 text-[12.5px] mb-4">
    <div>
        <div class="text-[10.5px] text-slate-400 mb-0.5">Billed to</div>
        <div class="font-medium text-ink">{{ $to['name'] ?? '—' }}</div><div class="text-slate-500">{{ $to['phone'] ?? '' }}</div><div class="text-slate-500">{{ $to['area'] ?? '' }}</div>
    </div>
    <div class="text-right">
        <div class="text-[10.5px] text-slate-400 mb-0.5">Booking / Technician</div>
        <div class="num text-brand-600">{{ $iv->booking?->ref ?? '—' }}</div><div class="text-slate-500">{{ $iv->technician?->name ?? '—' }}</div>
    </div>
</div>
<table class="w-full text-[12.5px] mb-4">
    <tbody>
    @forelse ($iv->items as $item)
        <tr class="border-b border-slate2">
            <td class="py-2">{{ $item->description }}@if ($item->quantity > 1) × {{ $item->quantity }}@endif</td>
            <td class="py-2 text-right num {{ $item->amount < 0 ? 'text-rose-500' : '' }}">{{ money($item->amount) }}</td>
        </tr>
    @empty
        {{-- invoices issued before line items existed --}}
        <tr class="border-b border-slate2"><td class="py-2">Service charges — {{ $iv->service_name }}</td><td class="py-2 text-right num">{{ money($iv->service_charge) }}</td></tr>
        <tr class="border-b border-slate2"><td class="py-2">Material charges</td><td class="py-2 text-right num">{{ money($iv->material_charge) }}</td></tr>
        <tr class="border-b border-slate2"><td class="py-2">Discount</td><td class="py-2 text-right num text-rose-500">−{{ money($iv->discount) }}</td></tr>
    @endforelse
    <tr class="border-b border-slate2"><td class="py-2">GST ({{ rtrim(rtrim(number_format($iv->tax_rate * 100, 2), '0'), '.') }}%)</td><td class="py-2 text-right num">{{ money($iv->tax) }}</td></tr>
    <tr><td class="py-3 font-semibold text-[14px]">Total</td><td class="py-3 text-right num font-bold text-[16px] text-ink">{{ money($iv->total) }}</td></tr>
    @if ($iv->amount_paid)
        <tr><td class="py-1 text-slate-500">Paid</td><td class="py-1 text-right num text-brand-600">−{{ money($iv->amount_paid) }}</td></tr>
        <tr><td class="py-1 font-semibold">Balance due</td><td class="py-1 text-right num font-semibold">{{ money($iv->balance()) }}</td></tr>
    @endif
    </tbody>
</table>
