@extends('layouts.app')
@section('title', 'Settings')

@section('content')
<x-page-head title="Settings" sub="Platform & WhatsApp configuration" />

<div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
    <div class="bg-white rounded-2xl border border-slate2 p-5">
        <div class="flex items-center gap-2 mb-4">
            <div class="w-8 h-8 rounded-lg bg-brand-50 text-brand-600 grid place-items-center">{!! crm_icon('chat', 'w-4 h-4') !!}</div>
            <span class="font-semibold text-ink text-[14px]">WhatsApp Cloud API</span>
            <span class="ml-auto text-[11px] {{ $apiStatus === 'Connected' ? 'text-brand-600 bg-brand-50' : 'text-amber bg-amber-50' }} px-2 py-0.5 rounded-full">● {{ $apiStatus }}</span>
        </div>
        <div class="space-y-3 text-[12.5px]">
            @foreach ($fields as $label => $value)
                <div>
                    <label class="text-[11px] text-slate-400">{{ $label }}</label>
                    <input value="{{ $value }}" readonly class="w-full mt-1 bg-paper border border-slate2 rounded-lg px-3 py-2 num text-slate-600 outline-none">
                </div>
            @endforeach
        </div>
        <p class="text-[11px] text-slate-400 mt-3">Credentials are read from <code class="num">.env</code> (<code class="num">WHATSAPP_*</code>) and never sent to the browser in full. Without a token, messages are stored in the inbox but not delivered.</p>
    </div>

    <div class="space-y-3">
        <div class="bg-white rounded-2xl border border-slate2 p-5">
            <div class="font-semibold text-ink text-[14px] mb-3">Branding</div>
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-brand grid place-items-center text-white font-display font-bold text-xl">H</div>
                <div class="text-[12.5px]"><div class="font-semibold">{{ config('crm.brand.name') }}</div><div class="text-slate-400">Home Maintenance & Waste Management</div></div>
            </div>
            <div class="grid grid-cols-2 gap-2 mt-4 text-[12px]">
                <div class="bg-paper rounded-lg p-2.5"><div class="text-[10.5px] text-slate-400">Currency</div>{{ $currency }}</div>
                <div class="bg-paper rounded-lg p-2.5"><div class="text-[10.5px] text-slate-400">Timezone</div>{{ $timezone }}</div>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate2 p-5">
            <div class="font-semibold text-ink text-[14px] mb-3">System health</div>
            <div class="grid grid-cols-2 gap-2 text-[12px]">
                @foreach ($health as $label => $n)
                    <div class="bg-paper rounded-lg p-2.5"><div class="text-[10.5px] text-slate-400">{{ $label }}</div><span class="num font-semibold {{ $n > 0 && ! str_starts_with($label, 'Queue') ? 'text-rose-600' : '' }}">{{ $n }}</span></div>
                @endforeach
            </div>
            <p class="text-[11px] text-slate-400 mt-2">Health check endpoint: <span class="num">{{ url('/up') }}</span></p>
        </div>

        @if (config('crm.demo_switcher'))
            <div class="bg-white rounded-2xl border border-slate2 p-5">
                <div class="font-semibold text-ink text-[14px] mb-2">Demo data</div>
                <p class="text-[12px] text-slate-500 mb-3">Wipes the database and reloads the seeded demo set (customers, bookings, chats, invoices). Everyone stays signed in.</p>
                <form method="POST" action="{{ route('settings.reset') }}" data-confirm="Reset all demo data? This deletes everything you've added.">
                    @csrf
                    <x-btn type="submit" kind="ghost">Reset demo data</x-btn>
                </form>
            </div>
        @endif
    </div>
</div>

@if ($audit->isNotEmpty())
    <div class="bg-white rounded-2xl border border-slate2 overflow-hidden mt-3">
        <div class="px-5 py-4 font-semibold text-ink text-[14px]">Audit log <span class="font-normal text-[12px] text-slate-400">· latest 25 · Super Admin only</span></div>
        <div class="overflow-x-auto">
            <table class="w-full text-[12.5px]">
                <thead class="bg-paper text-slate-500 text-[11.5px]"><tr>
                    <th class="text-left font-medium px-4 py-2">When</th><th class="text-left font-medium px-4 py-2">Who</th>
                    <th class="text-left font-medium px-4 py-2">Action</th><th class="text-left font-medium px-4 py-2 hidden md:table-cell">Change</th>
                </tr></thead>
                <tbody>
                @foreach ($audit as $log)
                    <tr class="border-t border-slate2">
                        <td class="px-4 py-2 text-slate-500 whitespace-nowrap">{{ local_time($log->created_at)->format('d M, h:i A') }}</td>
                        <td class="px-4 py-2">{{ $log->user?->name ?? 'System' }}</td>
                        <td class="px-4 py-2"><span class="num">{{ $log->action }}</span>@if ($log->entity_type) <span class="text-slate-400">· {{ $log->entity_type }} #{{ $log->entity_id }}</span>@endif</td>
                        <td class="px-4 py-2 hidden md:table-cell text-slate-500 num text-[11px] truncate max-w-[360px]">{{ $log->after ? json_encode($log->after, JSON_UNESCAPED_UNICODE) : '' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
