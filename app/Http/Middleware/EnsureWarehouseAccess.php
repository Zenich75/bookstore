<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\Warehouse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For routes with a {warehouse} parameter: an admin may open any warehouse,
 * a manager only the one they work at (users.warehouse_id).
 *
 * Route-level guard for a whole URL subtree (/manage/warehouses/{warehouse}/...).
 * WarehousePolicy::view() expresses the same rule per resource — for @can in
 * views and for code that has a Warehouse but no route (see docs/07).
 */
class EnsureWarehouseAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        // Route model binding (SubstituteBindings, web group) has already run: a model, not an id.
        $warehouse = $request->route('warehouse');
        $user = $request->user();

        abort_unless($warehouse instanceof Warehouse && $user !== null, 404);

        abort_unless(
            $user->role === UserRole::Admin
                || ($user->role === UserRole::Manager && $user->warehouse_id === $warehouse->id),
            403,
        );

        return $next($request);
    }
}
