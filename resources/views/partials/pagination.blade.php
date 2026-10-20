{{-- Compact pager in the template's style. Usage: @include('partials.pagination', ['p' => $paginator]) --}}
@if ($p->hasPages())
    <div class="flex items-center justify-between gap-3 px-4 py-3 border-t border-slate2 text-[12.5px]">
        <span class="text-slate-400">{{ $p->firstItem() }}–{{ $p->lastItem() }} of {{ $p->total() }}</span>
        <div class="flex items-center gap-1">
            @if ($p->onFirstPage())
                <span class="px-3 py-1.5 rounded-lg text-slate-300">‹ Prev</span>
            @else
                <a href="{{ $p->previousPageUrl() }}" class="px-3 py-1.5 rounded-lg bg-white border border-slate2 text-slate-600 hover:text-ink">‹ Prev</a>
            @endif
            <span class="px-2 text-slate-500 num">{{ $p->currentPage() }} / {{ $p->lastPage() }}</span>
            @if ($p->hasMorePages())
                <a href="{{ $p->nextPageUrl() }}" class="px-3 py-1.5 rounded-lg bg-white border border-slate2 text-slate-600 hover:text-ink">Next ›</a>
            @else
                <span class="px-3 py-1.5 rounded-lg text-slate-300">Next ›</span>
            @endif
        </div>
    </div>
@endif
