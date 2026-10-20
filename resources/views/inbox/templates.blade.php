<x-modal title="Send template" :sub="'To '.$conv->customer->name.' · '.$conv->customer->phone">
    @if ($conv->customer->whatsapp_opt_out)
        <div class="mb-3 text-[12px] text-rose-600 bg-rose-50 rounded-lg px-3 py-2">This customer opted out of WhatsApp messages (replied STOP). Templates can't be sent until they reply START.</div>
    @endif
    <form method="POST" action="{{ route('inbox.template', $conv) }}" class="space-y-2">
        @csrf
        @forelse ($templates as $t)
            @php($ok = $t->status === 'Approved' && ! $conv->customer->whatsapp_opt_out)
            <button type="submit" name="template_id" value="{{ $t->id }}" @disabled(! $ok)
                    class="w-full text-left p-3 rounded-xl border border-slate2 {{ $ok ? 'hover:bg-paper hover:border-brand-400' : 'opacity-50 cursor-not-allowed' }}">
                <div class="flex items-center justify-between mb-1">
                    <span class="num font-semibold text-[12.5px] text-ink">{{ $t->name }}</span>
                    <div class="flex items-center gap-1.5">
                        <x-badge color="bg-slate-100 text-slate-600">{{ $t->category }}</x-badge>
                        @if ($t->status !== 'Approved')<x-badge color="bg-amber-50 text-amber">{{ $t->status }}</x-badge>@endif
                    </div>
                </div>
                <div class="text-[12px] text-slate-500 whitespace-pre-line leading-snug line-clamp-3">{{ $t->body }}</div>
            </button>
        @empty
            <x-empty title="No templates" sub="Templates need Meta approval before they can be sent." />
        @endforelse
    </form>
</x-modal>
