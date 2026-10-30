<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class CorrelationId
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        // Do not echo untrusted header values into logs.
        $id = (string) Str::uuid();
        Log::withContext(['correlation_id' => $id]);
        try {
            $response = $next($request);
            $response->headers->set('X-Correlation-ID', $id);

            return $response;
        } finally {
            Log::withoutContext();
        }
    }
}
