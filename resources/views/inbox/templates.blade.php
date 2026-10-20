<x-modal title="Send template" :sub="'To '.$conv->customer->name.' · '.$conv->customer->phone">
    <form method="POST" action="{{ route('inbox.template', $conv) }}" class="space-y-2">
        @csrf
        @forelse ($templates as $t)
            <button type="submit" name="template_id" value="{{ $t->id }}" class="w-full text-left p-3 rounded-xl border border-slate2 hover:bg-paper hover:border-brand-400">
                <div class="flex items-center justify-between mb-1">
                    <span class="num font-semibold text-[12.5px] text-ink">{{ $t->name }}</span>
                    <x-badge color="bg-slate-100 text-slate-600">{{ $t->category }}</x-badge>
                </div>
                <div class="text-[12px] text-slate-500 whitespace-pre-line leading-snug line-clamp-3">{{ $t->body }}</div>
            </button>
        @empty
            <x-empty title="No approved templates" sub="Templates need Meta approval before they can be sent." />
        @endforelse
    </form>
</x-modal>
