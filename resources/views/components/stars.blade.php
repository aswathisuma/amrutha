@props(['n' => 0])
<span class="text-[13px]">@for ($i = 1; $i <= 5; $i++)<span class="{{ $i <= $n ? 'text-amber' : 'text-slate-300' }}">★</span>@endfor</span>
