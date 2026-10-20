<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Invoice;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index()
    {
        $branches = Branch::withCount(['bookings', 'technicians'])->get();
        $revenue = Invoice::where('invoices.status', 'Paid')
            ->join('bookings', 'bookings.id', '=', 'invoices.booking_id')
            ->groupBy('bookings.branch_id')
            ->selectRaw('bookings.branch_id, SUM(invoices.total) as total')
            ->pluck('total', 'branch_id');

        return view('branches.index', compact('branches', 'revenue'));
    }

    public function create()
    {
        return $this->panel('branches.create', [], route('branches'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'state' => ['required', 'string', 'max:120'],
            'manager_name' => ['nullable', 'string', 'max:120'],
        ]);
        Branch::create($data);

        return $this->done('Branch added');
    }
}
