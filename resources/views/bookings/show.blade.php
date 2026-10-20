@php($c = $b->customer)
@php($me = auth()->user())
@php($allowed = $b->allowedTransitions())
@php($exception = in_array($b->status, config('crm.exception_statuses'), true))
<x-drawer>
    <x-slot:header>
        <div class="num text-[17px] font-bold text-brand-600">{{ $b->ref }}</div>
        <div class="text-[12px] text-slate-500">{{ $b->service_name }} · {{ $b->area }}</div>
    </x-slot:header>

    <div class="bg-white rounded-2xl border border-slate2 p-4">
        <div class="flex items-center gap-3">
            <x-avatar :name="$c->name" :size="44" />
            <div class="flex-1"><div class="font-semibold text-ink">{{ $c->name }}</div><div class="text-[12px] text-slate-500">{{ $c->phone }}@if ($c->whatsapp_opt_out) · <span class="text-rose-500">WhatsApp opted out</span>@endif</div></div>
            @if ($me->canSee('inbox'))
                <a href="{{ route('inbox.customer', $c) }}" class="p-2 rounded-lg bg-brand-50 text-brand-600" title="Open WhatsApp">{!! crm_icon('chat', 'w-4 h-4') !!}</a>
            @endif
        </div>
        <div class="grid grid-cols-2 gap-y-2 gap-x-4 mt-3 text-[12.5px]">
            <div><div class="text-slate-400 text-[11px]">Date & time</div>{{ $b->scheduled_on->format('d M Y') }} · {{ $b->time_slot }}</div>
            <div><div class="text-slate-400 text-[11px]">Branch</div>{{ $b->branch?->name ?? '—' }}</div>
            <div><div class="text-slate-400 text-[11px]">Technician</div>{!! $b->technician ? e($b->technician->name) : '<span class="text-amber">Unassigned</span>' !!}
                @if ($b->assignedBy)<div class="text-[10.5px] text-slate-400">by {{ $b->assignedBy->name }} · {{ local_time($b->assigned_at)?->format('d M, h:i A') }}</div>@endif
            </div>
            <div><div class="text-slate-400 text-[11px]">Source</div>{{ $b->source ?? '—' }}</div>
            <div class="col-span-2"><div class="text-slate-400 text-[11px]">Problem</div>{{ $b->description }}</div>
            @if ($b->status_reason)
                <div class="col-span-2"><div class="text-slate-400 text-[11px]">{{ $exception ? $b->status.' reason' : 'Note' }}</div><span class="{{ $exception ? 'text-rose-600' : '' }}">{{ $b->status_reason }}</span></div>
            @endif
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate2 p-4">
        <div class="flex items-center justify-between mb-3">
            <span class="text-[12.5px] font-semibold">Status timeline</span>
            @if ($exception)<x-badge :status="$b->status" />@endif
        </div>
        <div>
            @foreach ($statuses as $i => $s)
                @php($done = $idx >= 0 && $i <= $idx)
                @php($h = $b->history->last(fn ($row) => $row->to_status === $s))
                <div class="flex gap-3 items-start">
                    <div class="flex flex-col items-center">
                        <div class="w-3.5 h-3.5 rounded-full {{ $done ? 'bg-brand' : 'bg-slate-200' }} grid place-items-center">@if ($done)<span class="text-white text-[8px]">✓</span>@endif</div>
                        @if (! $loop->last)<div class="w-px h-5 {{ $done ? 'bg-brand-400' : 'bg-slate-200' }}"></div>@endif
                    </div>
                    <div class="flex-1 flex items-baseline justify-between gap-2 -mt-0.5 pb-1">
                        <span class="text-[12px] {{ $done ? 'text-ink font-medium' : 'text-slate-400' }}">{{ $s }}</span>
                        @if ($h)<span class="text-[10.5px] text-slate-400 text-right">{{ $h->actorName() }} · {{ local_time($h->created_at)->format('d M, h:i A') }}</span>@endif
                    </div>
                </div>
            @endforeach
        </div>
        @php($exceptions = $b->history->whereIn('to_status', config('crm.exception_statuses')))
        @if ($exceptions->isNotEmpty())
            <div class="mt-3 pt-3 border-t border-slate2 space-y-1.5">
                @foreach ($exceptions as $h)
                    <div class="text-[11.5px]"><span class="font-medium text-rose-600">{{ $h->to_status }}</span> <span class="text-slate-500">— {{ $h->reason }}</span> <span class="text-slate-400">· {{ $h->actorName() }}, {{ local_time($h->created_at)->format('d M') }}</span></div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="grid grid-cols-3 gap-2 text-[13px]">
        <div class="bg-white rounded-xl border border-slate2 p-3"><div class="text-[11px] text-slate-400">Estimated</div><div class="num font-semibold">{{ money($b->est_amount) }}</div></div>
        <div class="bg-white rounded-xl border border-slate2 p-3"><div class="text-[11px] text-slate-400">Final</div><div class="num font-semibold">{{ $b->final_amount !== null ? money($b->subtotal()) : '—' }}</div>
            @if ($b->final_amount !== null && ! $b->charges_approved_at)<div class="text-[10px] text-amber">awaiting approval</div>@endif
        </div>
        <div class="bg-white rounded-xl border border-slate2 p-3"><div class="text-[11px] text-slate-400">Payment</div>
            <x-badge :color="$b->payment_status === 'Paid' ? 'bg-brand-50 text-brand-600' : 'bg-orange-50 text-orange-700'">{{ $b->payment_status === '—' ? 'Pending' : $b->payment_status }}</x-badge>
        </div>
    </div>

    @if ($b->work_notes || $b->attachments->isNotEmpty())
        <div class="bg-white rounded-2xl border border-slate2 p-4">
            <div class="text-[12.5px] font-semibold mb-2">Work notes & photos</div>
            @if ($b->work_notes)<p class="text-[12.5px] text-slate-600 whitespace-pre-line mb-2">{{ $b->work_notes }}</p>@endif
            <div class="flex flex-wrap gap-2">
                @foreach ($b->attachments as $a)
                    <a href="{{ $a->signedUrl() }}" target="_blank" rel="noopener" class="block w-16 h-16 rounded-lg border border-slate2 overflow-hidden bg-paper grid place-items-center text-[10px] text-slate-500" title="{{ $a->label }} · {{ $a->original_name }}">
                        @if ($a->isImage())<img src="{{ $a->signedUrl() }}" alt="{{ $a->label }} photo" class="w-full h-full object-cover">@else PDF @endif
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="flex flex-wrap gap-2">
        @if ($b->status === 'Service Completed')
            @if ($b->final_amount !== null && ! $b->charges_approved_at && $canApprove)
                <form method="POST" action="{{ route('bookings.charges.approve', $b) }}">@csrf<x-btn type="submit">Approve charges & invoice</x-btn></form>
            @endif
            <x-btn :kind="$b->final_amount !== null ? 'ghost' : 'primary'" :data-panel-url="route('bookings.charges-form', $b)">{{ $b->final_amount !== null ? 'Edit charges' : 'Review charges → Invoice' }}</x-btn>
        @elseif (in_array($b->status, ['Invoice Generated', 'Payment Pending'], true) && $b->invoice && $me->canSee('invoices'))
            @if ($b->status === 'Invoice Generated')
                <form method="POST" action="{{ route('bookings.advance', $b) }}">@csrf<x-btn type="submit" kind="ghost">Mark payment pending</x-btn></form>
            @endif
            <x-btn :data-panel-url="route('invoices.show', $b->invoice)">Record payment</x-btn>
        @elseif ($next && ! ($next === 'Assigned' && ! $b->technician_id))
            <form method="POST" action="{{ route('bookings.advance', $b) }}">@csrf<x-btn type="submit">Advance → {{ $next }}</x-btn></form>
        @endif

        @if (in_array($b->status, ['New', 'Confirmed', 'Assigned', 'Rescheduled'], true))
            <x-btn :kind="$b->technician_id ? 'ghost' : 'primary'" :data-panel-url="route('bookings.assign-form', $b)">{{ $b->technician_id ? 'Reassign' : 'Assign technician' }}</x-btn>
        @endif
        @if ($b->invoice && $me->canSee('invoices') && ! in_array($b->status, ['Invoice Generated', 'Payment Pending'], true))
            <x-btn kind="ghost" :data-panel-url="route('invoices.show', $b->invoice)">View invoice</x-btn>
        @endif
        @if ($me->canSee('inbox'))
            <x-btn kind="ghost" :href="route('inbox.customer', $c)">Send WhatsApp</x-btn>
        @endif
        @if ($b->technician_id && $b->status !== 'Cancelled')
            <x-btn kind="ghost" :data-panel-url="route('bookings.work-form', $b)">Notes & photos</x-btn>
        @endif
        @foreach (['Rescheduled' => 'Reschedule', 'No Show' => 'No show', 'Cancelled' => 'Cancel'] as $to => $label)
            @if (in_array($to, $allowed, true))
                <x-btn :kind="$to === 'Rescheduled' ? 'ghost' : 'danger'" :data-panel-url="route('bookings.status-form', [$b, 'action' => $to])">{{ $label }}</x-btn>
            @endif
        @endforeach
    </div>
    <p class="text-[11px] text-slate-400">Every change is checked against the booking workflow, recorded in the status history with who made it, and sends the matching WhatsApp update (unless its automation rule is off or the customer opted out).</p>
</x-drawer>
