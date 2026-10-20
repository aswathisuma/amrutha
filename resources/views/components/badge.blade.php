@props(['status' => null, 'color' => null])
<span {{ $attributes->merge(['class' => 'inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium '.($color ?? status_color($status))]) }}>{{ $status ?? $slot }}</span>
