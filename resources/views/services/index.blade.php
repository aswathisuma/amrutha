@extends('layouts.app')
@section('title', 'Services')

@section('content')
<x-page-head title="Services" sub="Categories your team can book">
    <x-btn :data-panel-url="route('services.create')">+ Add Service</x-btn>
</x-page-head>

<div class="space-y-4">
    @foreach ($categories as $cat)
        <div class="bg-white rounded-2xl border border-slate2 p-5">
            <div class="flex items-center justify-between mb-3">
                <div class="font-display font-bold text-[15px] text-ink">{{ $cat->name }}</div>
                <span class="text-[11.5px] text-slate-400">{{ $cat->services->count() }} services</span>
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach ($cat->services as $s)
                    <div class="group flex items-center gap-2 bg-paper rounded-xl px-3 py-2 text-[12.5px]">
                        <span class="w-1.5 h-1.5 rounded-full bg-brand-400"></span>{{ $s->name }}
                        @if ($s->base_price)<span class="num text-slate-400 text-[11px]">from {{ money($s->base_price) }}</span>@endif
                        <form method="POST" action="{{ route('services.destroy', $s) }}" data-confirm="Remove {{ $s->name }}?" class="contents">
                            @csrf @method('DELETE')
                            <button class="opacity-0 group-hover:opacity-100 focus:opacity-100 text-rose-400 hover:text-rose-600 ml-1" aria-label="Remove {{ $s->name }}">✕</button>
                        </form>
                    </div>
                @endforeach
                <button type="button" data-panel-url="{{ route('services.create', ['category' => $cat->id]) }}" class="flex items-center gap-1 px-3 py-2 rounded-xl border border-dashed border-slate-300 text-[12.5px] text-slate-500 hover:border-brand-400 hover:text-brand-600">+ Add</button>
            </div>
        </div>
    @endforeach
</div>
@endsection
