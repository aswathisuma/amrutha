@extends('layouts.app')
@section('title', 'Automation')

@section('content')
<x-page-head title="Automation Engine" sub="Rules that run your WhatsApp journey">
    <form method="POST" action="{{ route('automation.run') }}">@csrf<x-btn type="submit" kind="ghost" title="Run the time-based rules now (they also run every 5 minutes)">▶ Run now</x-btn></form>
    <x-btn data-toast="Status-triggered rules can be added via POST /api/v1/automation-rules. A rule builder UI is planned.">+ New Rule</x-btn>
</x-page-head>

<div class="space-y-2.5">
    @foreach ($rules as $a)
        <div class="bg-white rounded-2xl border border-slate2 p-4 flex items-center gap-4">
            <div class="flex-1 min-w-0">
                <div class="flex flex-col sm:flex-row sm:items-center gap-2 text-[13px]">
                    <span class="inline-flex items-center gap-1.5"><span class="text-[10px] font-bold text-amber bg-amber-50 px-1.5 py-0.5 rounded">IF</span><span class="text-slate-600">{{ $a->trigger }}</span></span>
                    <span class="text-slate-300 hidden sm:inline">→</span>
                    <span class="inline-flex items-center gap-1.5"><span class="text-[10px] font-bold text-brand-600 bg-brand-50 px-1.5 py-0.5 rounded">THEN</span><span class="text-ink font-medium">{{ $a->action }}</span></span>
                </div>
                <div class="text-[11px] text-slate-400 mt-1.5">
                    {{ $a->status_trigger ? 'On status change' : 'Scheduled · every 5 min' }}
                    @if ($a->template) · template <span class="num">{{ $a->template }}</span>@endif
                    · max {{ $a->max_attempts }} attempts
                    @if ($a->last_run_at)
                        · last run {{ ago($a->last_run_at) }} ago
                        <span class="{{ ['sent' => 'text-brand-600', 'skipped' => 'text-amber', 'failed' => 'text-rose-600'][$a->last_run_status] ?? '' }}">({{ $a->last_run_status }})</span>
                    @endif
                </div>
            </div>
            <button type="button" data-panel-url="{{ route('automation.runs', $a) }}" class="text-[11px] text-slate-400 num hidden md:block hover:text-brand-600" title="View execution log">{{ $a->runs }} runs</button>
            <form method="POST" action="{{ route('automation.toggle', $a) }}">
                @csrf
                <button role="switch" aria-checked="{{ $a->enabled ? 'true' : 'false' }}" aria-label="Toggle rule" class="relative w-10 h-6 rounded-full transition {{ $a->enabled ? 'bg-brand' : 'bg-slate-200' }}">
                    <span class="absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-all" style="left:{{ $a->enabled ? '18px' : '2px' }}"></span>
                </button>
            </form>
        </div>
    @endforeach
</div>
<p class="text-[11.5px] text-slate-400 mt-3">Each run is logged once under a unique key, so retries never send duplicates. Rules skip customers who opted out, templates not yet approved by Meta, and bookings or invoices whose state has moved on. Scheduled rules need <span class="num">php artisan schedule:work</span> (or a cron) in production.</p>
@endsection
