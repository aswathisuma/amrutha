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

        $jobs = Booking::with('customer')->withCount('attachments')->where('technician_id', $tech->id)->orderBy('scheduled_on')->orderBy('time_slot')->get();

        return view('field.index', [
            't' => $tech,
            // Technicians see only their own jobs and only what they need on site (spec §4.2 step 12).
            'active' => $jobs->whereIn('status', ['Assigned', 'Technician On The Way', 'Service Started', 'Service Completed', 'Rescheduled']),
            'completed' => $jobs->whereIn('status', Booking::PAID_STATUSES)->count(),
            'allTechs' => $user->technician ? collect() : Technician::orderBy('name')->get(),
        ]);
    }
}
