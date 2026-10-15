<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Terminable middleware: terminate() runs after the response has been sent to
 * the browser, so logging costs the user nothing.
 *
 * Laravel resolves terminable middleware from the container a second time for
 * terminate(). Without a singleton binding that is a NEW instance whose
 * $startedAt was never set. AppServiceProvider binds this class as a singleton,
 * so handle() and terminate() run on the same object and share the start time.
 */
class LogSlowRequests
{
    private ?int $startedAt = null;

    public function handle(Request $request, Closure $next): Response
    {
        $this->startedAt = hrtime(true);

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($this->startedAt === null) {
            return;
        }

        $ms = (hrtime(true) - $this->startedAt) / 1e6;
        $this->startedAt = null;

        if ($ms > config('app.slow_request_ms')) {
            Log::warning('Slow request', [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'status' => $response->getStatusCode(),
                'ms' => round($ms, 1),
                'user_id' => $request->user()?->id,
            ]);
        }
    }
}
