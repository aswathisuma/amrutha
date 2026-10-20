@props(['name' => '?', 'size' => 34])
@php
    $initials = collect(preg_split('/\s+/', trim($name)))->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->join('');
    $hues = ['#0f8f5b', '#2563eb', '#7c3aed', '#c77800', '#0891b2', '#db2777'];
    $h = 0;
    foreach (mb_str_split($name) as $ch) { $h += mb_ord($ch); }
@endphp
<div {{ $attributes->merge(['class' => 'rounded-full grid place-items-center text-white font-semibold shrink-0']) }}
     style="width:{{ $size }}px;height:{{ $size }}px;background:{{ $hues[$h % count($hues)] }};font-size:{{ $size * 0.36 }}px">{{ mb_strtoupper($initials) }}</div>
