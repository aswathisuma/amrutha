<x-modal title="New support ticket">
    <form method="POST" action="{{ route('support.store') }}" class="space-y-3 text-[13px]">
        @csrf
        <x-field label="Customer">
            <select name="customer_id" class="input">
                @foreach ($customers as $c)<option value="{{ $c->id }}">{{ $c->name }} · {{ $c->phone }}</option>@endforeach
            </select>
        </x-field>
        <x-field label="Issue"><input name="issue" required autofocus class="input" placeholder="e.g. Technician arrived late"></x-field>
        <div class="grid grid-cols-2 gap-2">
            <x-field label="Priority">
                <select name="priority" class="input">
                    @foreach (config('crm.ticket_priorities') as $p)<option @selected($p === 'Medium')>{{ $p }}</option>@endforeach
                </select>
            </x-field>
            <x-field label="Assign to">
                <select name="assigned_to" class="input">
                    <option value="">Unassigned</option>
                    @foreach ($staff as $name)<option>{{ $name }}</option>@endforeach
                </select>
            </x-field>
        </div>
        <div class="flex justify-end gap-2 pt-1">
            <x-btn kind="ghost" data-close-modal>Cancel</x-btn>
            <x-btn type="submit">Create ticket</x-btn>
        </div>
    </form>
</x-modal>
