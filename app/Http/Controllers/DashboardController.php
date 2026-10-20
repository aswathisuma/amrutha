<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    /** Full activity timeline (drawer behind the dashboard's "Timeline" link). */
    public function activity()
    {
        return $this->panel('dashboard.activity', [
            'days' => Activity::latest()->take(100)->get()->groupBy(fn ($a) => $a->created_at->format('Y-m-d')),
        ], route('dashboard'));
    }

    public function __invoke(Request $request)
    {
        $user = $request->user();
        $range = in_array($request->query('range'), ['today', 'week', 'month', 'custom'], true) ? $request->query('range') : 'month';
        $to = now();
        $from = match ($range) {
            'today' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            default => now()->startOfMonth(),
        };
        if ($range === 'custom') {
            try {
                $from = Carbon::parse($request->query('from', now()->startOfMonth()))->startOfDay();
                $to = Carbon::parse($request->query('to', now()))->endOfDay();
            } catch (\Throwable) {
                $range = 'month';
            }
        }

        $B = $user->scopeBookingQuery(Booking::query())->get();
        $count = fn (array $statuses) => $B->whereIn('status', $statuses)->count();

        // Paid invoices limited to the bookings this user can see.
        $invoices = Invoice::whereIn('booking_id', $B->pluck('id'))->get();
        $paid = $invoices->where('status', 'Paid');

        $reviews = Review::all();

        $stats = [
            'customers' => $user->isBranchScoped() ? Customer::where('branch_id', $user->branch_id)->count() : Customer::count(),
            'newCustomers' => Customer::where('created_at', '>=', now()->subWeek())->count(),
            'newLeads' => Lead::where('stage', 'New Lead')->count(),
            'today' => $B->filter(fn ($b) => $b->scheduled_on->isToday())->count(),
            'pending' => $count(['New', 'Confirmed']),
            'assigned' => $count(['Assigned']),
            'inProgress' => $count(['Technician On The Way', 'Service Started']),
            'done' => $count(['Service Completed', 'Invoice Generated', 'Payment Pending', 'Paid', 'Closed']),
            'cancelled' => $count(['Cancelled', 'No Show']),
            'avgRating' => number_format((float) $reviews->avg('rating'), 1),
            'reviews' => $reviews->count(),
            'revRange' => $paid->filter(fn ($i) => $i->paid_at?->between($from, $to))->sum('total'),
            'revToday' => $paid->filter(fn ($i) => $i->paid_at?->isToday())->sum('total'),
            'outstanding' => $invoices->where('status', '!=', 'Paid')->sum('total'),
        ];

        $flow = [
            'New' => $count(['New']),
            'Confirmed' => $count(['Confirmed']),
            'Assigned' => $stats['assigned'],
            'In Progress' => $stats['inProgress'],
            'Completed' => $count(['Service Completed']),
            'Paid' => $count(Booking::PAID_STATUSES),
        ];

        // Last 7 days: bookings created and revenue collected per day.
        $days = collect(range(6, 0))->map(fn ($d) => now()->subDays($d)->startOfDay());
        $trend = [
            'labels' => $days->map->format('D')->all(),
            'bookings' => $days->map(fn ($d) => $B->filter(fn ($b) => $b->created_at->isSameDay($d))->count())->all(),
            'revenue' => $days->map(fn ($d) => $paid->filter(fn ($i) => $i->paid_at?->isSameDay($d))->sum('total'))->all(),
        ];

        $serviceMix = $B->groupBy('service_name')->map->count();

        return view('dashboard.index', [
            'stats' => $stats,
            'flow' => $flow,
            'trend' => $trend,
            'serviceMix' => $serviceMix,
            'range' => $range,
            'from' => $from,
            'to' => $to,
            'activity' => Activity::latest()->take(8)->get(),
            'scopeLabel' => $user->isBranchScoped() ? $user->branch->name : \App\Models\Branch::count().' branches',
        ]);
    }
}
