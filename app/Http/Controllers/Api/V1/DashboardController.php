<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\DashboardMetrics;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends ApiController
{
    /** GET /dashboard?from=&to=&branch_id= (dates in the display timezone) */
    public function __invoke(Request $request, DashboardMetrics $metrics)
    {
        $this->requireSection($request, 'dashboard');
        $f = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);
        $tz = config('crm.display_timezone');
        $from = isset($f['from']) ? Carbon::parse($f['from'], $tz)->startOfDay() : local_now()->startOfMonth();
        $to = isset($f['to']) ? Carbon::parse($f['to'], $tz)->endOfDay() : local_now()->endOfDay();

        return $this->respond($metrics->compute($request->user(), $from->utc(), $to->utc(), $f['branch_id'] ?? null));
    }
}
