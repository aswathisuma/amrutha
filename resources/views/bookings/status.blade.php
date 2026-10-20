@php($titles = ['Rescheduled' => 'Reschedule booking', 'Cancelled' => 'Cancel booking', 'No Show' => 'Mark as no show'])
<x-modal :title="$titles[$action]" :sub="$b->ref.' · '.$b->service_name.' · '.$b->scheduled_on->format('d M').' '.$b->time_slot">
    <form method="POST" action="{{ route('bookings.status', $b) }}" class="space-y-3 text-[13px]">
        @csrf
        <input type="hidden" name="status" value="{{ $action }}">
        @if ($action === 'Rescheduled')
            <div class="grid grid-cols-2 gap-2">
                <x-field label="New date"><input name="scheduled_on" type="date" min="{{ local_now()->toDateString() }}" value="{{ max($b->scheduled_on->toDateString(), local_now()->toDateString()) }}" required class="input"></x-field>
                <x-field label="Time">
                    <select name="time_slot" class="input">@foreach (config('crm.time_slots') as $slot)<option @selected($slot === $b->time_slot)>{{ $slot }}</option>@endforeach</select>
                </x-field>
            </div>
        @endif
        <x-field label="Reason (required — saved in the booking history)">
            <input name="reason" required maxlength="255" autofocus class="input" placeholder="{{ ['Rescheduled' => 'e.g. Customer asked for a later date', 'Cancelled' => 'e.g. Customer found another provider', 'No Show' => 'e.g. Nobody home, called twice'][$action] }}">
        </x-field>
        <div class="flex justify-end gap-2 pt-1">
            <x-btn kind="ghost" data-close-modal>Back</x-btn>
            <x-btn type="submit" :kind="$action === 'Rescheduled' ? 'primary' : 'danger'">{{ $titles[$action] }}</x-btn>
        </div>
    </form>
</x-modal>
