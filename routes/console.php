<?php

use App\Models\IdempotencyKey;
use App\Models\User;
use App\Services\AutomationRunner;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

/*
| Time-based automations (spec §4.6). Production: run `php artisan schedule:work`
| (or a cron for `schedule:run` every minute) plus `php artisan queue:work`.
*/
Artisan::command('crm:automations', function (AutomationRunner $runner) {
    $counts = $runner->runScheduled();
    $this->info($counts ? collect($counts)->map(fn ($n, $k) => "$k: $n")->join(', ') : 'Nothing due.');
})->purpose('Run reminder, payment, feedback and follow-up automations');

Schedule::command('crm:automations')->everyFiveMinutes()->withoutOverlapping();
Schedule::call(fn () => IdempotencyKey::where('created_at', '<', now()->subDays(2))->delete())->daily();

/*
| Admin bootstrap (spec §14): create the first Super Admin on a fresh
| production database instead of shipping demo accounts.
*/
Artisan::command('crm:create-admin {email} {--name=Administrator} {--password=}', function (string $email) {
    if (User::where('email', $email)->exists()) {
        $this->error("A user with {$email} already exists.");

        return 1;
    }

    $password = $this->option('password') ?: Str::password(16);
    User::create(['name' => $this->option('name'), 'email' => $email, 'password' => $password, 'role' => 'super', 'status' => 'Active']);

    $this->info("Super Admin {$email} created.");
    if (! $this->option('password')) {
        $this->warn("Temporary password (shown once): {$password}");
    }

    return 0;
})->purpose('Create a Super Admin account');

/*
| Remove the seeded demo accounts before launch (spec §14).
*/
Artisan::command('crm:remove-demo-users', function () {
    $emails = ['anjali@hayfixr.in', 'rahul@hayfixr.in', 'fahad@hayfixr.in', 'deepa@hayfixr.in', 'sneha@hayfixr.in', 'vinod@hayfixr.in'];
    if (! User::whereNotIn('email', $emails)->where('role', 'super')->exists()) {
        $this->error('Create your own Super Admin first (php artisan crm:create-admin you@company.com).');

        return 1;
    }
    $n = User::whereIn('email', $emails)->delete();
    $this->info("Removed {$n} demo account(s).");

    return 0;
})->purpose('Delete the seeded demo logins');
