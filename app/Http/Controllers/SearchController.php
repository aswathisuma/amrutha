<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Technician;
use Illuminate\Http\Request;

/** Feeds the ⌘K command palette. */
class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $q = trim((string) $request->query('q'));
        $results = [];

        if ($q === '') {
            foreach (config('crm.nav') as $items) {
                foreach ($items as $id => $item) {
                    if ($user->canSee($id)) {
                        $results[] = ['t' => $item['label'], 's' => 'Go to page', 'url' => route($id)];
                    }
                }
            }

            return response()->json($results);
        }

        $like = '%'.$q.'%';

        if ($user->canSee('customers')) {
            Customer::where(fn ($w) => $w->where('name', 'like', $like)->orWhere('phone', 'like', $like))
                ->when($user->isBranchScoped(), fn ($w) => $w->where('branch_id', $user->branch_id))
                ->limit(4)->get()
                ->each(function ($c) use (&$results) {
                    $results[] = ['t' => $c->name, 's' => 'Customer · '.$c->phone, 'drawer' => route('customers.show', $c)];
                });
        }

        if ($user->canSee('bookings')) {
            $user->scopeBookingQuery(Booking::query())
                ->where(fn ($w) => $w->where('ref', 'like', $like)->orWhere('service_name', 'like', $like))
                ->limit(4)->get()
                ->each(function ($b) use (&$results) {
                    $results[] = ['t' => $b->ref, 's' => 'Booking · '.$b->service_name, 'drawer' => route('bookings.show', $b)];
                });
        }

        if ($user->canSee('invoices')) {
            InvoiceController::scoped($user)->where('number', 'like', $like)->limit(3)->get()
                ->each(function ($i) use (&$results) {
                    $results[] = ['t' => $i->number, 's' => 'Invoice · '.money($i->total), 'modal' => route('invoices.show', $i)];
                });
        }

        if ($user->canSee('technicians')) {
            Technician::where('name', 'like', $like)->limit(3)->get()
                ->each(function ($t) use (&$results) {
                    $results[] = ['t' => $t->name, 's' => 'Technician · '.$t->skill, 'url' => route('technicians')];
                });
        }

        return response()->json($results);
    }
}
