<x-modal title="Add lead">
    <form method="POST" action="{{ route('leads.store') }}" class="space-y-3 text-[13px]">
        @csrf
        <div class="grid grid-cols-2 gap-2">
            <x-field label="Name"><input name="name" required autofocus class="input"></x-field>
            <x-field label="Phone"><input name="phone" value="+91" class="input"></x-field>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <x-field label="Service">
                <select name="service_name" class="input">
                    @foreach ($services as $s)<option>{{ $s->name }}</option>@endforeach
                </select>
            </x-field>
            <x-field label="Source">
                <select name="source" class="input">
                    @foreach (config('crm.sources') as $src)<option @selected($src === 'Walk-in')>{{ $src }}</option>@endforeach
                </select>
            </x-field>
        </div>
        <div class="grid grid-cols-3 gap-2">
            <x-field label="Est. value (₹)"><input name="value" type="number" min="0" step="100" value="600" class="input"></x-field>
            <x-field label="Area"><input name="area" class="input" placeholder="e.g. Kakkanad"></x-field>
            <x-field label="Branch">
                <select name="branch_id" class="input">
                    @foreach ($branches as $b)<option value="{{ $b->id }}" @selected(auth()->user()->branch_id === $b->id)>{{ $b->name }}</option>@endforeach
                </select>
            </x-field>
        </div>
        <p class="text-[11px] text-slate-400 -mt-1">If the phone matches an existing customer, the lead is linked to them automatically.</p>
        <x-field label="Note"><textarea name="note" rows="2" class="input"></textarea></x-field>
        <div class="flex justify-end gap-2 pt-1">
            <x-btn kind="ghost" data-close-modal>Cancel</x-btn>
            <x-btn type="submit">Add lead</x-btn>
        </div>
    </form>
</x-modal>
