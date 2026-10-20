@php($me = auth()->user())
<div data-panel="modal" class="p-6">
    @include('invoices._body')
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex items-center gap-2">
            <x-badge :color="$iv->statusColor()">{{ $iv->status }}</x-badge>
            @if ($iv->isPaid())<span class="text-[11px] text-slate-400">{{ $iv->method }} · {{ $iv->paid_at?->format('d M') }}</span>@endif
        </div>
        <div class="flex flex-wrap gap-2">
            @if (! $iv->isPaid() && $me->canSee('payments'))
                <form method="POST" action="{{ route('payments.paid', $iv) }}">@csrf<x-btn type="submit" kind="ghost">Mark paid</x-btn></form>
            @endif
            @if ($me->canSee('inbox'))
                <x-btn kind="ghost" :href="route('inbox.customer', $iv->customer)">Send on WhatsApp</x-btn>
            @endif
            <x-btn :href="route('invoices.print', $iv)" target="_blank">Print / PDF</x-btn>
        </div>
    </div>
</div>
