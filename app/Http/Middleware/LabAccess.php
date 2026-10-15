<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lab pages run heavy demo queries and rolled-back writes: open in local and
 * testing environments, elsewhere for admins only.
 */
class LabAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(self::allows($request->user()), 403);

        return $next($request);
    }

    public static function allows(?User $user): bool
    {
        return app()->environment('local', 'testing') || $user?->role === UserRole::Admin;
    }
}
