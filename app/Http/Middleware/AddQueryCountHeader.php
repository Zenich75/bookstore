<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * "After" middleware: does its work once $next() has produced the response.
 * Adds X-Query-Count (number of SQL queries of the request) in the local
 * environment — a cheap N+1 alarm in the browser's network tab (stage 4).
 */
class AddQueryCountHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('local')) {
            return $next($request);
        }

        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });

        $response = $next($request);

        // Everything below runs after the controller and the view.
        $response->headers->set('X-Query-Count', (string) $count);

        return $response;
    }
}
