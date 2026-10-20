<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Service;
use App\Models\Technician;
use Illuminate\Http\Request;

class TechnicianController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $techs = Technician::with('branch')
            ->when($user->isBranchScoped(), fn ($q) => $q->where('branch_id', $user->branch_id))
            ->orderBy('name')
            ->get();

        return view('technicians.index', compact('techs'));
    }

    public function create()
    {
        return $this->panel('technicians.create', [
            'branches' => Branch::orderBy('name')->get(),
            'services' => Service::orderBy('name')->get(),
        ], route('technicians'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20'],
            'skill' => ['required', 'string', 'max:120'],
            'branch_id' => ['required', 'exists:branches,id'],
        ]);
        Technician::create($data + ['status' => 'Available', 'rating' => 5.0]);

        return $this->done('Technician added');
    }
}
