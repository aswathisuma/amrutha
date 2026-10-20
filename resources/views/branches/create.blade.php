<x-modal title="Add branch">
    <form method="POST" action="{{ route('branches.store') }}" class="space-y-3 text-[13px]">
        @csrf
        <x-field label="Branch name"><input name="name" required autofocus class="input"></x-field>
        <div class="grid grid-cols-2 gap-2">
            <x-field label="City"><input name="city" required class="input"></x-field>
            <x-field label="District"><input name="district" class="input"></x-field>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <x-field label="State"><input name="state" value="Kerala" required class="input"></x-field>
            <x-field label="Manager"><input name="manager_name" class="input"></x-field>
        </div>
        <div class="flex justify-end gap-2 pt-1">
            <x-btn kind="ghost" data-close-modal>Cancel</x-btn>
            <x-btn type="submit">Add branch</x-btn>
        </div>
    </form>
</x-modal>
