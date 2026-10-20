<?php

use Carbon\Carbon;

if (! function_exists('money')) {
    /** Format a rupee amount with Indian digit grouping, e.g. ₹1,23,456. */
    function money($n): string
    {
        $n = (int) round((float) $n);
        $s = (string) abs($n);
        if (strlen($s) > 3) {
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($s, 0, -3));
            $s = $rest.','.substr($s, -3);
        }

        return ($n < 0 ? '−' : '').'₹'.$s;
    }
}

if (! function_exists('ago')) {
    function ago($date): string
    {
        $m = Carbon::parse($date)->diffInMinutes(now(), true);
        if ($m < 1) {
            return 'now';
        }
        if ($m < 60) {
            return floor($m).'m';
        }
        if ($m < 1440) {
            return floor($m / 60).'h';
        }

        return floor($m / 1440).'d';
    }
}

if (! function_exists('status_color')) {
    function status_color(?string $status): string
    {
        return config('crm.status_colors')[$status] ?? 'bg-slate-100 text-slate-600';
    }
}

if (! function_exists('crm_icon')) {
    function crm_icon(string $name, string $cls = 'w-[18px] h-[18px]'): string
    {
        $paths = config('crm.icons')[$name] ?? '';

        return '<svg class="'.e($cls).'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">'.$paths.'</svg>';
    }
}
