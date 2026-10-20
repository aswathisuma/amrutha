@props(['kind' => 'primary', 'href' => null])
@php
    $styles = [
        'primary' => 'bg-brand text-white hover:bg-brand-600',
        'ghost' => 'bg-white border border-slate2 text-ink hover:bg-paper',
        'danger' => 'bg-rose-50 text-rose-600 hover:bg-rose-100',
    ];
    $cls = 'inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-[13px] font-medium transition '.$styles[$kind];
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $cls]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => $cls]) }}>{{ $slot }}</button>
@endif
