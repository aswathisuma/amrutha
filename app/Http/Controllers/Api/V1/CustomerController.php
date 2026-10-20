<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\CrmService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends ApiController
{
    /** GET /customers?q= (name or phone) */
    public function index(Request $request)
    {
        $this->requireSection($request, 'customers');
        $q = trim((string) ($request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? ''));
        $user = $request->user();
        $digits = preg_replace('/\D/', '', $q);

        $query = Customer::query()
            ->when($user->isBranchScoped(), fn ($w) => $w->where('branch_id', $user->branch_id))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%$q%")
                ->when($digits !== '', fn ($y) => $y->orWhere('phone', 'like', "%$digits%"))));

        return $this->paginate($query, $request, CustomerResource::class, ['id', 'name', 'created_at'], 'name');
    }

    /** POST /customers — 409 duplicate_customer if the phone is already registered. */
    public function store(Request $request, CrmService $crm)
    {
        $this->requireSection($request, 'customers');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email'],
            'area' => ['nullable', 'string', 'max:120'],
            'branch_id' => ['required', 'exists:branches,id'],
            'type' => ['required', Rule::in(['Residential', 'Commercial'])],
        ]);
        if ($request->user()->isBranchScoped()) {
            $data['branch_id'] = $request->user()->branch_id;
        }

        return $this->created(new CustomerResource($crm->createCustomer($data, $request->user())));
    }
}
