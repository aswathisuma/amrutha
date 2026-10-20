@props(['title' => null, 'sub' => null])
{{-- Content for the centred dialog. Loaded via fetch into #modal. --}}
<div data-panel="modal" {{ $attributes->merge(['class' => 'p-5']) }}>
    @if ($title)
        <div class="flex items-start justify-between mb-4">
            <div>
                <div class="font-semibold text-[15px]">{{ $title }}</div>
                @if ($sub)<p class="text-[12px] text-slate-500 mt-0.5">{{ $sub }}</p>@endif
            </div>
            <button type="button" data-close-modal class="p-1 -mr-1 rounded-lg hover:bg-paper text-slate-400" aria-label="Close">✕</button>
        </div>
    @endif
    {{ $slot }}
</div>
