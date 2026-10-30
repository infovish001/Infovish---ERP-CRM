<?php

namespace App\Http\Middleware;

use App\Platform\Tenancy\TenantRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveTenantHost
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());
        if (in_array($host, config('tenancy.platform_hosts'), true)) {
            abort_unless(in_array($request->path(), ['/', 'health', 'ready', 'up'], true), 404);

            return $next($request);
        }
        $tenant = app(TenantRegistry::class)->forHost($host);
        abort_unless($tenant && $tenant->status === 'active', 404);
        $request->attributes->set('tenant', $tenant);
        $previous = ['session.cookie' => config('session.cookie'), 'session.domain' => config('session.domain')];
        config(['session.cookie' => 'infovish_'.substr(hash('sha256', $host), 0, 16), 'session.domain' => null]);
        // Fortify can resolve its guard/session while Laravel gathers controller middleware,
        // before this middleware runs. Keep that already-created store on the host cookie too.
        $session = app('session')->driver();
        $previousName = $session->getName();
        $session->setName(config('session.cookie'));
        if ($request->is('login') && $request->isMethod('post')) {
            $request->merge(['remember' => false]);
        }
        if ($request->is('user/two-factor-authentication') && $request->isMethod('post')) {
            $request->merge(['force' => false]);
        }
        try {
            $response = $next($request);
            $response->headers->set('Cache-Control', 'no-store, private');

            return $response;
        } finally {
            $session->setName($previousName);
            config($previous);
        }
    }
}
