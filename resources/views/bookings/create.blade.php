<x-modal title="New booking">
    <form method="POST" action="{{ route('bookings.store') }}" class="space-y-3 text-[13px]">
        @csrf
        <x-field label="Customer">
            <select name="customer_id" class="input" required>
                @foreach ($customers as $c)
                    <option value="{{ $c->id }}" @selected($selected === $c->id)>{{ $c->name }} · {{ $c->area }}</option>
                @endforeach
            </select>
        </x-field>
        <x-field label="Service">
            <select name="service_id" class="input" required>
                @foreach ($services as $s)
                    <option value="{{ $s->id }}">{{ $s->name }} · from {{ money($s->base_price) }}</option>
                @endforeach
            </select>
        </x-field>
        <div class="grid grid-cols-2 gap-2">
            <x-field label="Date"><input name="scheduled_on" type="date" value="{{ today()->toDateString() }}" class="input"></x-field>
            <x-field label="Time">
                <select name="time_slot" class="input">
                    @foreach (config('crm.time_slots') as $slot)<option>{{ $slot }}</option>@endforeach
                </select>
            </x-field>
        </div>
        <x-field label="Problem description">
            <textarea name="description" rows="2" class="input" placeholder="Describe the issue…"></textarea>
        </x-field>
        <div class="flex justify-end gap-2 pt-1">
            <x-btn kind="ghost" data-close-modal>Cancel</x-btn>
            <x-btn type="submit">Create booking</x-btn>
        </div>
    </form>
</x-modal>
