<?php

namespace App\Providers;

use App\Models\Conversation;
use App\Models\Invoice;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Rate limits (spec §10 / §14): API per user (or IP), login per email+IP.
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(5)->by(mb_strtolower((string) $r->input('email')).'|'.$r->ip()));

        // Shell data: sidebar badge, notification bell, demo user switcher.
        View::composer('layouts.app', function ($view) {
            $unread = (int) Conversation::sum('unread');
            $notifications = [];

            if ($c = Conversation::with('customer')->where('unread', '>', 0)->orderByDesc('last_message_at')->first()) {
                $notifications[] = ['icon' => 'chat', 'title' => 'New WhatsApp enquiry', 'sub' => $c->customer->name.' · '.ago($c->last_message_at).' ago', 'color' => 'text-brand-600', 'url' => route('inbox', ['c' => $c->id]), 'section' => 'inbox'];
            }
            if ($i = Invoice::where('status', 'Paid')->latest('paid_at')->first()) {
                $notifications[] = ['icon' => 'card', 'title' => 'Payment received', 'sub' => money($i->total).' · '.($i->method ?? 'UPI'), 'color' => 'text-brand-600', 'url' => route('payments'), 'section' => 'payments'];
            }
            if ($t = Ticket::whereIn('status', ['Open', 'Assigned'])->latest()->first()) {
                $notifications[] = ['icon' => 'life', 'title' => 'New complaint', 'sub' => $t->issue, 'color' => 'text-amber', 'url' => route('support'), 'section' => 'support'];
            }

            $user = auth()->user();
            $notifications = array_values(array_filter($notifications, fn ($n) => $user?->canSee($n['section'])));

            $view->with([
                'navUnread' => $unread,
                'notifications' => $notifications,
                'switchUsers' => config('crm.demo_switcher') ? User::orderBy('id')->get() : collect(),
            ]);
        });
    }
}
