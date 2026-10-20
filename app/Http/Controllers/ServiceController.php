<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index()
    {
        return view('services.index', ['categories' => ServiceCategory::with('services')->get()]);
    }

    public function create(Request $request)
    {
        return $this->panel('services.create', [
            'categories' => ServiceCategory::all(),
            'selected' => $request->integer('category') ?: null,
        ], route('services'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id' => ['required', 'exists:service_categories,id'],
            'name' => ['required', 'string', 'max:120'],
            'base_price' => ['nullable', 'integer', 'min:0'],
        ]);
        Service::create($data);

        return $this->done('Service added');
    }

    public function destroy(Service $service)
    {
        $service->delete();

        return $this->done('Service removed', 'warn');
    }
}
