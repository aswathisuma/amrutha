<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Support\Facades\Artisan;

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
                'Webhook Verify Token' => $wa['verify_token'],
                'Webhook URL' => route('webhook.whatsapp'),
            ],
            'apiStatus' => $wa['token'] ? 'Connected' : 'Local mode',
            'currency' => Setting::get('currency', '₹ INR'),
            'timezone' => Setting::get('timezone', 'IST (UTC+5:30)'),
        ]);
    }

    /** Wipe and reseed all CRM data (demo environments only). */
    public function reset()
    {
        abort_unless(config('crm.demo_switcher'), 403);
        Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);

        return $this->done('Demo data reset');
    }
}
