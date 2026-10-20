<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard for role-based sections: `crm:bookings,field` lets through any
 * user whose role can see at least one of the listed sections.
 */
class EnsureCanSee
{
    public function handle(Request $request, Closure $next, string ...$sections): Response
    {
        $user = $request->user();

        foreach ($sections as $section) {
            if ($user?->canSee($section)) {
                return $next($request);
            }
        }

        abort(403, 'Your role does not have access to this section.');
    }
}
