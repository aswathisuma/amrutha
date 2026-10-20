<x-modal title="Final charges" :sub="$b->ref.' · '.$b->service_name.' · estimate '.money($b->est_amount)">
    <form method="POST" action="{{ route('bookings.charges', $b) }}" class="space-y-3 text-[13px]">
        @csrf
        <div class="grid grid-cols-3 gap-2">
            <x-field label="Service (₹)"><input name="final_amount" type="number" min="0" step="1" required value="{{ $b->final_amount ?? $b->est_amount }}" class="input" autofocus></x-field>
            <x-field label="Materials (₹)"><input name="material_charge" type="number" min="0" step="1" value="{{ $b->material_charge ?: 0 }}" class="input"></x-field>
            <x-field label="Discount (₹)"><input name="discount" type="number" min="0" step="1" value="{{ $b->discount ?: 0 }}" class="input"></x-field>
        </div>
        <p class="text-[11.5px] text-slate-500">
            GST of {{ config('crm.gst_rate') * 100 }}% is added on the invoice. Totals more than {{ round($threshold * 100) }}% away from the estimate
            @if ($canApprove) are approved by you on save. @else need a manager or admin to approve before the invoice is issued. @endif
            The invoice is generated once and its amounts are frozen.
        </p>
        <div class="flex justify-end gap-2 pt-1">
            <x-btn kind="ghost" data-close-modal>Cancel</x-btn>
            <x-btn type="submit">Save & generate invoice</x-btn>
        </div>
    </form>
</x-modal>
