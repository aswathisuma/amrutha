@props(['title', 'sub' => null])
<div class="flex flex-wrap items-end justify-between gap-3 mb-5">
    <div>
        <h1 class="font-display text-[22px] font-bold text-ink tracking-tight">{{ $title }}</h1>
        @if ($sub)
            <p class="text-[13px] text-slate-500 mt-0.5">{{ $sub }}</p>
        @endif
    </div>
    <div class="flex items-center gap-2">{{ $slot }}</div>
</div>
