<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * KPI aggregates for the dashboard and GET /api/v1/dashboard, computed from
 * persisted records and limited to the user's branch/technician scope.
 * Revenue = payment ledger (net of refunds) received in the period.
 */
class DashboardMetrics
{
    public function compute(User $user, Carbon $from, Carbon $to, ?int $branchId = null): array
    {
        if ($user->isBranchScoped()) {
            $branchId = $user->branch_id; // managers can't widen their scope
        }

        $bookingQ = $user->scopeBookingQuery(Booking::query())->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
        $B = (clone $bookingQ)->get(['id', 'status', 'service_name', 'scheduled_on', 'created_at', 'branch_id']);
        $count = fn (array $s) => $B->whereIn('status', $s)->count();

        $invoiceIds = Invoice::whereIn('booking_id', $B->pluck('id'))->pluck('id');
        $ledger = Payment::whereIn('invoice_id', $invoiceIds);
        $todayLocal = local_now();

        $reviews = Review::query()->when($branchId, fn ($q) => $q->whereHas('customer', fn ($c) => $c->where('branch_id', $branchId)));

        return [
            'period' => ['from' => $from->toIso8601String(), 'to' => $to->toIso8601String()],
            'customers' => Customer::when($branchId, fn ($q) => $q->where('branch_id', $branchId))->count(),
            'new_customers_7d' => Customer::when($branchId, fn ($q) => $q->where('branch_id', $branchId))->where('created_at', '>=', now()->subWeek())->count(),
            'new_leads' => Lead::where('stage', 'New Lead')->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->count(),
            'bookings_today' => $B->filter(fn ($b) => $b->scheduled_on->isSameDay($todayLocal))->count(),
            'pending' => $count(['New', 'Confirmed']),
            'assigned' => $count(['Assigned']),
            'in_progress' => $count(['Technician On The Way', 'Service Started']),
            'completed' => $count(Booking::DONE_STATUSES),
            'cancelled' => $count(['Cancelled', 'No Show']),
            'avg_rating' => round((float) (clone $reviews)->avg('rating'), 1),
            'reviews' => (clone $reviews)->count(),
            'revenue_period' => (int) (clone $ledger)->whereBetween('received_at', [$from, $to])->sum('amount'),
            'revenue_today' => (int) (clone $ledger)->whereBetween('received_at', [$todayLocal->copy()->startOfDay()->utc(), $todayLocal->copy()->endOfDay()->utc()])->sum('amount'),
            'outstanding' => (int) Invoice::whereIn('id', $invoiceIds)->whereIn('status', ['Pending', 'Partial'])->get()->sum(fn ($i) => $i->balance()),
            'flow' => [
                'New' => $count(['New']),
                'Confirmed' => $count(['Confirmed']),
                'Assigned' => $count(['Assigned']),
                'In Progress' => $count(['Technician On The Way', 'Service Started']),
                'Completed' => $count(['Service Completed']),
                'Paid' => $count(Booking::PAID_STATUSES),
            ],
            'trend' => $this->trend($B, $invoiceIds),
            'service_mix' => $B->groupBy('service_name')->map->count(),
        ];
    }

    /** Last 7 local days: bookings created and net revenue received. */
    protected function trend($B, $invoiceIds): array
    {
        $days = collect(range(6, 0))->map(fn ($d) => local_now()->subDays($d)->startOfDay());
        $payments = Payment::whereIn('invoice_id', $invoiceIds)->where('received_at', '>=', $days->first()->copy()->utc())->get(['amount', 'received_at']);

        return [
            'labels' => $days->map->format('D')->all(),
            'bookings' => $days->map(fn ($d) => $B->filter(fn ($b) => local_time($b->created_at)->isSameDay($d))->count())->all(),
            'revenue' => $days->map(fn ($d) => (int) $payments->filter(fn ($p) => local_time($p->received_at)->isSameDay($d))->sum('amount'))->all(),
        ];
    }
}
