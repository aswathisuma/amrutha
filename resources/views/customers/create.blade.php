<x-modal title="Add customer">
    <form method="POST" action="{{ route('customers.store') }}" class="space-y-3 text-[13px]">
        @csrf
        <x-field label="Full name"><input name="name" required autofocus class="input"></x-field>
        <div class="grid grid-cols-2 gap-2">
            <x-field label="Phone (WhatsApp)"><input name="phone" value="+91" required class="input"></x-field>
            <x-field label="Email"><input name="email" type="email" class="input"></x-field>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <x-field label="Area"><input name="area" class="input" placeholder="e.g. Edappally"></x-field>
            <x-field label="Branch">
                <select name="branch_id" class="input">
                    @foreach ($branches as $b)<option value="{{ $b->id }}" @selected(auth()->user()->branch_id === $b->id)>{{ $b->name }}</option>@endforeach
                </select>
            </x-field>
        </div>
        <x-field label="Type">
            <select name="type" class="input"><option>Residential</option><option>Commercial</option></select>
        </x-field>
        <div class="flex justify-end gap-2 pt-1">
            <x-btn kind="ghost" data-close-modal>Cancel</x-btn>
            <x-btn type="submit">Add customer</x-btn>
        </div>
    </form>
</x-modal>
