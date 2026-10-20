<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Technician;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        $revenue = Invoice::where('invoices.status', 'Paid')
            ->join('bookings', 'bookings.id', '=', 'invoices.booking_id')
            ->groupBy('bookings.branch_id')
            ->selectRaw('bookings.branch_id, SUM(invoices.total) as total')
            ->pluck('total', 'branch_id');
        $branches = Branch::orderBy('id')->get();

        // Funnel: how many leads reached at least each stage (excluding "Lost").
        $stages = array_slice(config('crm.lead_stages'), 0, 6);
        $leadIdx = Lead::pluck('stage')->map(fn ($s) => array_search($s, config('crm.lead_stages'), true));
        $funnel = collect($stages)->map(fn ($s, $i) => $leadIdx->filter(fn ($x) => $x !== false && $x >= $i && $x < 6)->count());

        $techs = Technician::orderByDesc('jobs_done')->take(6)->get();

        $services = $user->scopeBookingQuery(Booking::query())
            ->selectRaw('service_name, COUNT(*) as n')->groupBy('service_name')->pluck('n', 'service_name');

        return view('reports.index', [
            'charts' => [
                'branch' => ['labels' => $branches->pluck('name'), 'data' => $branches->map(fn ($b) => (int) ($revenue[$b->id] ?? 0))],
                'funnel' => ['labels' => $stages, 'data' => $funnel],
                'tech' => ['labels' => $techs->map(fn ($t) => explode(' ', $t->name)[0]), 'data' => $techs->pluck('jobs_done')],
                'service' => ['labels' => $services->keys(), 'data' => $services->values()],
            ],
        ]);
    }
}
