<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Message;
use App\Models\Setting;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    public function index()
    {
        $wa = config('crm.whatsapp');
        $mask = fn (?string $v) => $v ? substr($v, 0, 4).str_repeat('•', 24) : 'Not configured';

        return view('settings.index', [
            'fields' => [
                'Phone Number ID' => $wa['phone_id'] ?: 'Not configured',
                'Business Account ID' => $wa['business_id'] ?: 'Not configured',
                'Access Token' => $mask($wa['token']),
                'App Secret (webhook signatures)' => $mask($wa['app_secret']),
                'Webhook Verify Token' => $wa['verify_token'] ? $mask($wa['verify_token']) : 'Not configured',
                'Webhook URL' => route('webhook.whatsapp'),
            ],
            'apiStatus' => $wa['token'] ? 'Connected' : 'Local mode',
            'currency' => Setting::get('currency', '₹ INR'),
            'timezone' => config('crm.display_timezone').' (stored as UTC)',
            'health' => [
                'Queue (pending jobs)' => DB::table('jobs')->count(),
                'Failed jobs' => DB::table('failed_jobs')->count(),
                'Failed WhatsApp sends (7d)' => Message::where('status', 'failed')->where('updated_at', '>=', now()->subWeek())->count(),
                'Webhook events failed (7d)' => WebhookEvent::where('status', 'failed')->where('received_at', '>=', now()->subWeek())->count(),
            ],
            'audit' => auth()->user()->role === 'super' ? AuditLog::with('user')->latest('id')->take(25)->get() : collect(),
        ]);
    }

    /** Wipe and reseed demo data. Never available in production (spec §14). */
    public function reset()
    {
        abort_unless(config('crm.demo_switcher') && ! app()->isProduction(), 403);
        Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);

        return $this->done('Demo data reset');
    }
}
