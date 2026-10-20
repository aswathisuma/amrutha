@extends('layouts.app')
@section('title', 'Automation')

@section('content')
<x-page-head title="Automation Engine" sub="Rules that run your WhatsApp journey">
    <x-btn data-toast="Rule builder — coming soon. Rules are seeded in database/seeders.">+ New Rule</x-btn>
</x-page-head>

<div class="space-y-2.5">
    @foreach ($rules as $a)
        <div class="bg-white rounded-2xl border border-slate2 p-4 flex items-center gap-4">
            <div class="flex-1 flex flex-col sm:flex-row sm:items-center gap-2 text-[13px]">
                <span class="inline-flex items-center gap-1.5"><span class="text-[10px] font-bold text-amber bg-amber-50 px-1.5 py-0.5 rounded">IF</span><span class="text-slate-600">{{ $a->trigger }}</span></span>
                <span class="text-slate-300 hidden sm:inline">→</span>
                <span class="inline-flex items-center gap-1.5"><span class="text-[10px] font-bold text-brand-600 bg-brand-50 px-1.5 py-0.5 rounded">THEN</span><span class="text-ink font-medium">{{ $a->action }}</span></span>
            </div>
            <div class="text-[11px] text-slate-400 num hidden md:block">{{ $a->runs }} runs</div>
            <form method="POST" action="{{ route('automation.toggle', $a) }}">
                @csrf
                <button role="switch" aria-checked="{{ $a->enabled ? 'true' : 'false' }}" aria-label="Toggle rule" class="relative w-10 h-6 rounded-full transition {{ $a->enabled ? 'bg-brand' : 'bg-slate-200' }}">
                    <span class="absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-all" style="left:{{ $a->enabled ? '18px' : '2px' }}"></span>
                </button>
            </form>
        </div>
    @endforeach
</div>
<p class="text-[11.5px] text-slate-400 mt-3">Status-based rules fire when a booking is advanced. Disabling a rule stops its WhatsApp message; time-based rules (reminders, follow-ups) need the Laravel scheduler in production.</p>
@endsection
