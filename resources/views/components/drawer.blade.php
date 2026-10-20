{{-- Content for the right-hand slide-over. Loaded via fetch into #drawer. --}}
<div data-panel="drawer">
    <div class="sticky top-0 bg-paper/90 backdrop-blur border-b border-slate2 px-5 py-4 flex items-center justify-between z-10">
        <div>{{ $header }}</div>
        <button type="button" data-close-drawer class="p-2 rounded-lg hover:bg-white text-slate-400" aria-label="Close">✕</button>
    </div>
    <div {{ $attributes->merge(['class' => 'p-5 space-y-4 text-[13px]']) }}>
        {{ $slot }}
    </div>
</div>
