<?php

namespace App\Http\Middleware;

use App\Shared\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequirePrivilegedMfa
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (app(TenantContext::class)->privileged() && ! $request->user()->two_factor_confirmed_at) {
            return $request->expectsJson() ? response()->json(['message' => 'Confirm multi-factor authentication before continuing.'], 403) : redirect('/account/security');
        }

        return $next($request);
    }
}
