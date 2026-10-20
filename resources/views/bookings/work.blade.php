<x-modal title="Work notes & photos" :sub="$b->ref.' · '.$b->service_name">
    <form method="POST" action="{{ route('bookings.work', $b) }}" enctype="multipart/form-data" class="space-y-3 text-[13px]">
        @csrf
        <x-field label="Work notes">
            <textarea name="work_notes" rows="3" class="input" placeholder="What was done, parts used, follow-ups…">{{ $b->work_notes }}</textarea>
        </x-field>
        <div class="grid grid-cols-3 gap-2">
            <div class="col-span-2">
                <x-field label="Photos (JPG/PNG/WEBP/PDF, up to 6 × 8 MB)">
                    <input name="photos[]" type="file" multiple accept="image/*,application/pdf" capture="environment" class="block w-full text-[12px] file:mr-2 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-brand-50 file:text-brand-600">
                </x-field>
            </div>
            <x-field label="Label">
                <select name="label" class="input"><option value="before">Before</option><option value="after" selected>After</option><option value="receipt">Receipt</option><option value="other">Other</option></select>
            </x-field>
        </div>
        @if ($b->attachments->isNotEmpty())
            <div class="flex flex-wrap gap-2">
                @foreach ($b->attachments as $a)
                    <a href="{{ $a->signedUrl() }}" target="_blank" rel="noopener" class="block w-14 h-14 rounded-lg border border-slate2 overflow-hidden bg-paper grid place-items-center text-[10px] text-slate-500">
                        @if ($a->isImage())<img src="{{ $a->signedUrl() }}" alt="{{ $a->label }} photo" class="w-full h-full object-cover">@else PDF @endif
                    </a>
                @endforeach
            </div>
        @endif
        <p class="text-[11px] text-slate-400">Files are stored privately and opened through links that expire after 10 minutes.</p>
        <div class="flex justify-end gap-2 pt-1">
            <x-btn kind="ghost" data-close-modal>Cancel</x-btn>
            <x-btn type="submit">Save</x-btn>
        </div>
    </form>
</x-modal>
