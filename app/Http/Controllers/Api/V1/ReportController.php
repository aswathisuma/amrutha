<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Technician;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends ApiController
{
    /** GET /reports/operations?from=&to=&branch_id= */
    public function operations(Request $request)
    {
        $this->requireSection($request, 'reports');
        $f = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);
        $user = $request->user();
        $branchId = $user->isBranchScoped() ? $user->branch_id : ($f['branch_id'] ?? null);
        $tz = config('crm.display_timezone');
        $from = Carbon::parse($f['from'] ?? local_now()->startOfMonth(), $tz)->startOfDay()->utc();
        $to = Carbon::parse($f['to'] ?? local_now(), $tz)->endOfDay()->utc();

        $bookings = Booking::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereBetween('created_at', [$from, $to]);

        $revenueByBranch = Payment::query()
            ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->join('bookings', 'bookings.id', '=', 'invoices.booking_id')
            ->whereBetween('payments.received_at', [$from, $to])
            ->when($branchId, fn ($q) => $q->where('bookings.branch_id', $branchId))
            ->groupBy('bookings.branch_id')
            ->selectRaw('bookings.branch_id, SUM(payments.amount) as revenue')
            ->pluck('revenue', 'branch_id');

        return $this->respond([
            'period' => ['from' => $from->toIso8601String(), 'to' => $to->toIso8601String()],
            'bookings_by_status' => (clone $bookings)->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status'),
            'bookings_by_service' => (clone $bookings)->selectRaw('service_name, COUNT(*) as n')->groupBy('service_name')->pluck('n', 'service_name'),
            'revenue_by_branch' => Branch::when($branchId, fn ($q) => $q->whereKey($branchId))->get()
                ->map(fn ($b) => ['branch_id' => $b->id, 'branch' => $b->name, 'revenue' => (int) ($revenueByBranch[$b->id] ?? 0)]),
            'lead_funnel' => Lead::when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->whereBetween('created_at', [$from, $to])->selectRaw('stage, COUNT(*) as n')->groupBy('stage')->pluck('n', 'stage'),
            'technicians' => Technician::when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->withCount(['bookings as completed_in_period' => fn ($q) => $q->whereIn('status', Booking::DONE_STATUSES)->whereBetween('completed_at', [$from, $to])])
                ->get()->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'completed' => $t->completed_in_period, 'rating' => $t->rating]),
        ]);
    }
}
