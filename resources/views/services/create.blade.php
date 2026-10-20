<x-modal title="Add service">
    <form method="POST" action="{{ route('services.store') }}" class="space-y-3 text-[13px]">
        @csrf
        <x-field label="Category">
            <select name="category_id" class="input">
                @foreach ($categories as $c)<option value="{{ $c->id }}" @selected($selected === $c->id)>{{ $c->name }}</option>@endforeach
            </select>
        </x-field>
        <x-field label="Service name"><input name="name" required autofocus class="input"></x-field>
        <x-field label="Starting price (₹) — leave blank if quoted on site"><input name="base_price" type="number" min="0" step="50" class="input"></x-field>
        <div class="flex justify-end gap-2 pt-1">
            <x-btn kind="ghost" data-close-modal>Cancel</x-btn>
            <x-btn type="submit">Add service</x-btn>
        </div>
    </form>
</x-modal>
