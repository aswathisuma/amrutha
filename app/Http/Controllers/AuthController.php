<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function showLogin()
    {
        $demoUsers = config('crm.demo_switcher') ? User::orderBy('id')->get() : collect();

        return view('auth.login', compact('demoUsers'));
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $key = 'web-login:'.mb_strtolower($credentials['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.'])->onlyInput('email');
        }

        if (! Auth::attempt($credentials + ['status' => 'Active'], $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);

            return back()->withErrors(['email' => 'These credentials do not match our records.'])->onlyInput('email');
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->user()->update(['last_login_at' => now()]);
        AuditLog::record('auth.login', $request->user());

        return redirect()->intended(route('home'));
    }

    public function logout(Request $request)
    {
        AuditLog::record('auth.logout', $request->user());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /** Demo-only: jump between seeded accounts to preview each role. */
    public function switchUser(Request $request)
    {
        abort_unless(config('crm.demo_switcher'), 404);

        $user = User::findOrFail($request->integer('user_id'));
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route($user->homeSection())->with('toast', ['Viewing as '.$user->roleLabel(), 'ok']);
    }
}
