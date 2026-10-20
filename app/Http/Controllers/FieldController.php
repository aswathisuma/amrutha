<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Technician;
use Illuminate\Http\Request;

class FieldController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        // Technicians see their own jobs; admins can preview any technician's view.
        $tech = $user->technician
            ?? ($user->role !== 'tech' ? (Technician::find($request->integer('tech')) ?? Technician::orderBy('id')->first()) : null);
        abort_unless($tech, 404, 'No technician profile is linked to this account.');

        $jobs = Booking::with('customer')->where('technician_id', $tech->id)->orderBy('scheduled_on')->get();

        return view('field.index', [
            't' => $tech,
            'active' => $jobs->whereNotIn('status', [...Booking::PAID_STATUSES, 'Cancelled', 'No Show']),
            'completed' => $jobs->whereIn('status', Booking::PAID_STATUSES)->count(),
            'allTechs' => $user->technician ? collect() : Technician::orderBy('name')->get(),
        ]);
    }
}
