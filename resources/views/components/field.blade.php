@props(['label'])
<div>
    <label class="text-[11px] text-slate-400">{{ $label }}</label>
    <div class="mt-1">{{ $slot }}</div>
</div>
