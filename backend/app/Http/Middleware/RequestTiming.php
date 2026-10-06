<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequestTiming
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/*')) {
            return $next($request);
        }
        $started = hrtime(true);
        $metrics = (object) ['queries' => 0, 'milliseconds' => 0.0];
        $request->attributes->set('lodge.query_metrics', $metrics);
        $response = $next($request);
        $elapsed = (hrtime(true) - $started) / 1e6;
        // Only durations and counts; never include SQL, bindings, or credentials.
        $response->headers->set('Server-Timing', sprintf('app;dur=%.2f, db;dur=%.2f, queries;desc="%d"', $elapsed, $metrics->milliseconds, $metrics->queries));

        return $response;
    }
}
