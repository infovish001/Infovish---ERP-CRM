<?php

namespace App\Http\Middleware;

use App\Platform\Identity\User;
use App\Platform\Tenancy\TenantExecution;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

final class EnforceTenantSession
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $execution = app(TenantExecution::class);
        $execution->clear();
        try {
            $tenant = $request->attributes->get('tenant');
            if (! $tenant) {
                return $next($request);
            }
            if ($request->is('two-factor-challenge') && $request->session()->has('login.id')) {
                $challenged = User::find($request->session()->get('login.id'));
                abort_unless($challenged && $challenged->active && $request->session()->get('challenge.tenant_id') === $tenant->id
                    && $request->session()->get('challenge.version') === $challenged->session_version
                    && DB::connection('pgsql')->table('memberships')->where('tenant_id', $tenant->id)->where('user_id', $challenged->id)->where('active', true)->exists(), 403);
            }
            $user = $request->user();
            if (! $user) {
                return $next($request);
            }
            $user = User::find($user->getAuthIdentifier());
            if (! $user || ! $user->active || $request->session()->get('tenant_login_id') !== $tenant->id || $request->session()->get('identity_version') !== $user->session_version) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                abort(401, 'Please sign in again.');
            }
            Auth::setUser($user);
            if (! $request->isMethod('get') && in_array($request->path(), ['user/confirm-password', 'user/confirmed-two-factor-authentication', 'account/password'], true)) {
                $key = 'identity-sensitive:'.$tenant->id.':'.$user->id.':'.$request->path();
                abort_if(RateLimiter::tooManyAttempts($key, 5), 429, 'Too many attempts. Please try again shortly.');
                RateLimiter::hit($key, 60);
            }

            return $execution->run($tenant->id, (string) $user->getAuthIdentifier(), fn () => $next($request));
        } finally {
            $execution->clear();
        }
    }
}
