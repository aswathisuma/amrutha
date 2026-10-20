<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends ApiController
{
    /** POST /auth/login → short-lived bearer token. */
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:60'],
        ]);

        $user = User::where('email', $data['email'])->first();
        if (! $user || $user->status !== 'Active' || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        $minutes = (int) config('sanctum.expiration');
        $token = $user->createToken($data['device_name'] ?? 'api', ['*'], $minutes ? now()->addMinutes($minutes) : null);
        $user->update(['last_login_at' => now()]);
        auth()->setUser($user);
        AuditLog::record('auth.api_login', $user);

        return $this->respond([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user' => $this->identity($user),
        ]);
    }

    /** POST /auth/logout — revoke the token used for this request. */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }

    /** GET /me — identity, role, branch scope and permitted sections. */
    public function me(Request $request)
    {
        return $this->respond($this->identity($request->user()));
    }

    protected function identity(User $u): array
    {
        $nav = config("crm.roles.{$u->role}.nav");

        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->role,
            'role_label' => $u->roleLabel(),
            'branch_scope' => $u->isBranchScoped() ? [$u->branch_id] : ($u->role === 'tech' ? [] : 'all'),
            'technician_id' => $u->technician_id,
            'permissions' => $nav === '*' ? collect(config('crm.nav'))->flatMap(fn ($i) => array_keys($i))->values() : $nav,
        ];
    }
}
