<?php

namespace App\Http\Controllers;

use App\Models\Automation;

class AutomationController extends Controller
{
    public function index()
    {
        return view('automation.index', ['rules' => Automation::orderBy('id')->get()]);
    }

    public function toggle(Automation $automation)
    {
        $automation->update(['enabled' => ! $automation->enabled]);

        return $this->done('Rule '.($automation->enabled ? 'enabled' : 'disabled'));
    }
}
