@props(['label', 'value', 'sub' => '', 'rule' => 'bg-brand', 'icon' => 'grid'])
<div class="bg-white rounded-2xl border border-slate2 p-4 relative overflow-hidden">
    <div class="absolute left-0 top-3 bottom-3 w-1 rounded-r {{ $rule }}"></div>
    <div class="pl-2">
        <div class="flex items-center justify-between">
            <span class="text-[12px] text-slate-500">{{ $label }}</span>
            <span class="text-slate-300">{!! crm_icon($icon, 'w-4 h-4') !!}</span>
        </div>
        <div class="num text-[26px] font-bold text-ink mt-1 leading-none">{{ $value }}</div>
        <div class="text-[11.5px] text-slate-400 mt-1.5">{{ $sub }}</div>
    </div>
</div>
