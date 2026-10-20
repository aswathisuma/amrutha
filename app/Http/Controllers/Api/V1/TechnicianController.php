<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\TechnicianResource;
use App\Models\Technician;
use Illuminate\Http\Request;

class TechnicianController extends ApiController
{
    /** GET /technicians?branch_id=&skill=&available=1 */
    public function index(Request $request)
    {
        $this->requireSection($request, 'technicians', 'bookings');
        $f = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'skill' => ['nullable', 'string', 'max:120'],
            'available' => ['nullable', 'boolean'],
        ]);
        $user = $request->user();

        $q = Technician::query()
            ->when($user->isBranchScoped(), fn ($q) => $q->where('branch_id', $user->branch_id))
            ->when($f['branch_id'] ?? null, fn ($q, $v) => $q->where('branch_id', $v))
            ->when($f['skill'] ?? null, fn ($q, $v) => $q->where('skill', $v))
            ->when($request->boolean('available'), fn ($q) => $q->where('status', '!=', 'Off Duty'));

        return $this->paginate($q, $request, TechnicianResource::class, ['id', 'name', 'rating', 'load'], 'name');
    }
}
