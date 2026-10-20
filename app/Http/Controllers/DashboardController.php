<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Branch;
use App\Services\DashboardMetrics;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    /** Full activity timeline (drawer behind the dashboard's "Timeline" link). */
    public function activity()
    {
        return $this->panel('dashboard.activity', [
            'days' => Activity::latest()->latest('id')->take(100)->get()->groupBy(fn ($a) => local_time($a->created_at)->format('Y-m-d')),
        ], route('dashboard'));
    }

    public function __invoke(Request $request, DashboardMetrics $metrics)
    {
        $user = $request->user();
        $tz = config('crm.display_timezone');
        $range = in_array($request->query('range'), ['today', 'week', 'month', 'custom'], true) ? $request->query('range') : 'month';
        $to = local_now()->endOfDay();
        $from = match ($range) {
            'today' => local_now()->startOfDay(),
            'week' => local_now()->startOfWeek(),
            default => local_now()->startOfMonth(),
        };
        if ($range === 'custom') {
            try {
                $from = Carbon::parse($request->query('from', $from), $tz)->startOfDay();
                $to = Carbon::parse($request->query('to', $to), $tz)->endOfDay();
            } catch (\Throwable) {
                $range = 'month';
            }
        }

        $m = $metrics->compute($user, $from->copy()->utc(), $to->copy()->utc());

        return view('dashboard.index', [
            'stats' => [
                'customers' => $m['customers'],
                'newCustomers' => $m['new_customers_7d'],
                'newLeads' => $m['new_leads'],
                'today' => $m['bookings_today'],
                'pending' => $m['pending'],
                'assigned' => $m['assigned'],
                'inProgress' => $m['in_progress'],
                'done' => $m['completed'],
                'cancelled' => $m['cancelled'],
                'avgRating' => number_format($m['avg_rating'], 1),
                'reviews' => $m['reviews'],
                'revRange' => $m['revenue_period'],
                'revToday' => $m['revenue_today'],
                'outstanding' => $m['outstanding'],
            ],
            'flow' => $m['flow'],
            'trend' => $m['trend'],
            'serviceMix' => $m['service_mix'],
            'range' => $range,
            'from' => $from,
            'to' => $to,
            'activity' => Activity::latest()->latest('id')->take(8)->get(),
            'scopeLabel' => $user->isBranchScoped() ? $user->branch->name : Branch::count().' branches',
        ]);
    }
}
