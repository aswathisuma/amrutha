<x-modal title="Add technician">
    <form method="POST" action="{{ route('technicians.store') }}" class="space-y-3 text-[13px]">
        @csrf
        <x-field label="Full name"><input name="name" required autofocus class="input"></x-field>
        <x-field label="Phone"><input name="phone" value="+91" required class="input"></x-field>
        <div class="grid grid-cols-2 gap-2">
            <x-field label="Primary skill">
                <select name="skill" class="input">
                    @foreach ($services as $s)<option>{{ $s->name }}</option>@endforeach
                </select>
            </x-field>
            <x-field label="Branch">
                <select name="branch_id" class="input">
                    @foreach ($branches as $b)<option value="{{ $b->id }}" @selected(auth()->user()->branch_id === $b->id)>{{ $b->name }}</option>@endforeach
                </select>
            </x-field>
        </div>
        <div class="flex justify-end gap-2 pt-1">
            <x-btn kind="ghost" data-close-modal>Cancel</x-btn>
            <x-btn type="submit">Add technician</x-btn>
        </div>
    </form>
</x-modal>
