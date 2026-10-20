@php($me = auth()->user())
<div data-panel="modal" class="p-6">
    @include('invoices._body')

    @if ($iv->payments->isNotEmpty())
        <div class="mb-4">
            <div class="text-[11px] uppercase tracking-wider text-slate-400 mb-1.5">Payment ledger</div>
            <div class="bg-paper rounded-xl divide-y divide-slate2 text-[12px]">
                @foreach ($iv->payments as $p)
                    <div class="flex items-center justify-between px-3 py-2">
                        <div>
                            <span class="font-medium {{ $p->type === 'refund' ? 'text-rose-600' : 'text-ink' }}">{{ ucfirst($p->type) }}</span>
                            <span class="text-slate-400">· {{ $p->method }}{{ $p->reference ? ' · '.$p->reference : '' }} · {{ local_time($p->received_at)->format('d M, h:i A') }}</span>
                            @if ($p->note)<div class="text-[11px] text-slate-400">{{ $p->note }}</div>@endif
                        </div>
                        <span class="num font-semibold {{ $p->amount < 0 ? 'text-rose-600' : 'text-brand-600' }}">{{ money($p->amount) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if (in_array($iv->status, ['Pending', 'Partial'], true) && $me->canSee('payments'))
        <form method="POST" action="{{ route('payments.paid', $iv) }}" class="bg-paper rounded-xl p-3 mb-4 grid grid-cols-2 sm:grid-cols-4 gap-2 items-end text-[12.5px]">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
            <input type="hidden" name="reopen" value="1">
            <x-field label="Amount (₹)"><input name="amount" type="number" min="1" max="{{ $iv->balance() }}" value="{{ $iv->balance() }}" @unless (config('crm.allow_partial_payments')) readonly @endunless class="input"></x-field>
            <x-field label="Method">
                <select name="method" class="input">@foreach (config('crm.pay_methods') as $m)<option @selected($m === 'UPI')>{{ $m }}</option>@endforeach</select>
            </x-field>
            <x-field label="Reference"><input name="reference" class="input" placeholder="UPI ref / receipt #"></x-field>
            <x-btn type="submit" class="justify-center">Record payment</x-btn>
        </form>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex items-center gap-2">
            <x-badge :color="$iv->statusColor()">{{ $iv->status }}</x-badge>
            @if ($iv->isPaid())<span class="text-[11px] text-slate-400">{{ $iv->method }} · {{ local_time($iv->paid_at)?->format('d M') }}</span>@endif
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($iv->amount_paid > 0 && in_array($me->role, config('crm.refund_roles'), true))
                <details class="relative">
                    <summary class="list-none cursor-pointer inline-flex items-center px-3.5 py-2 rounded-xl text-[13px] font-medium bg-rose-50 text-rose-600 hover:bg-rose-100">Refund</summary>
                    <form method="POST" action="{{ route('payments.refund', $iv) }}" data-confirm="Record a refund on {{ $iv->number }}? This adds a ledger entry and cannot be undone." class="absolute right-0 bottom-full mb-2 w-64 bg-white rounded-2xl shadow-soft border border-slate2 p-3 space-y-2 text-[12.5px] z-10">
                        @csrf
                        <x-field label="Amount (₹)"><input name="amount" type="number" min="1" max="{{ $iv->amount_paid }}" value="{{ $iv->amount_paid }}" required class="input"></x-field>
                        <x-field label="Reason"><input name="reason" required class="input" placeholder="e.g. Job redone free of charge"></x-field>
                        <x-btn type="submit" kind="danger" class="w-full justify-center">Record refund</x-btn>
                    </form>
                </details>
            @endif
            @if ($me->canSee('inbox'))
                <x-btn kind="ghost" :href="route('inbox.customer', $iv->customer)">Send on WhatsApp</x-btn>
            @endif
            <x-btn :href="route('invoices.print', $iv)" target="_blank">Print / PDF</x-btn>
        </div>
    </div>
</div>
