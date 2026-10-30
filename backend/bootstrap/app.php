<?php

use App\Http\Middleware\CorrelationId;
use App\Http\Middleware\EnforceTenantSession;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequirePrivilegedMfa;
use App\Http\Middleware\ResolveTenantHost;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(CorrelationId::class);
        $middleware->web(prepend: [ResolveTenantHost::class], append: [EnforceTenantSession::class, HandleInertiaRequests::class]);
        $middleware->alias(['privileged-mfa' => RequirePrivilegedMfa::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['code', 'recovery_code', 'two_factor_secret', 'database_password']);
    })->create();
