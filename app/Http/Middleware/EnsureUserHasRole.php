<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level role check: ->middleware('role:manager,admin').
 *
 * Everything after the colon arrives as extra handle() arguments, split on commas.
 * Runs after "auth", so a user is always present on these routes.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // UserRole::from() throws on a typo in the route definition ("role:admn") —
        // better a loud error than a route that silently lets nobody in.
        $allowed = array_map(fn (string $role) => UserRole::from($role), $roles);

        abort_unless(in_array($request->user()?->role, $allowed, true), 403);

        return $next($request);
    }
}
