<?php

namespace App\Http\Controllers;

use App\Models\WaTemplate;

class TemplateController extends Controller
{
    public function __invoke()
    {
        return view('templates.index', ['templates' => WaTemplate::orderBy('id')->get()]);
    }
}
