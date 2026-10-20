<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Automation;
use App\Services\AutomationRunner;

class AutomationController extends Controller
{
    public function index()
    {
        return view('automation.index', ['rules' => Automation::orderBy('id')->get()]);
    }

    public function toggle(Automation $automation)
    {
        $automation->update(['enabled' => ! $automation->enabled]);
        AuditLog::record('automation.toggled', $automation, ['enabled' => ! $automation->enabled], ['enabled' => $automation->enabled]);

        return $this->done('Rule '.($automation->enabled ? 'enabled' : 'disabled'));
    }

    /** Execution log for one rule (spec §4.6: every rule has an execution log). */
    public function runs(Automation $automation)
    {
        return $this->panel('automation.runs', [
            'rule' => $automation,
            'runs' => $automation->runs()->take(100)->get(),
        ], route('automation'));
    }

    /** Run time-based rules now (same code path as the scheduler; de-duplicated). */
    public function runNow(AutomationRunner $runner)
    {
        $counts = $runner->runScheduled();

        return $this->done($counts ? 'Ran '.collect($counts)->map(fn ($n, $k) => "$k ×$n")->join(', ') : 'Nothing due right now');
    }
}
