<?php

namespace App\Providers;

use App\Platform\Identity\User;
use App\Shared\Audit\Audit;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Timebox;
use Inertia\Inertia;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\Fortify;

class IdentityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('Login', ['tenantName' => $request->attributes->get('tenant')->name]));
        Fortify::twoFactorChallengeView(fn () => Inertia::render('Challenge'));
        Fortify::confirmPasswordView(fn () => Inertia::render('ConfirmPassword'));
        Fortify::authenticateUsing(function (Request $request) {
            return (new Timebox)->call(function () use ($request) {
                $tenant = $request->attributes->get('tenant');
                $user = User::where('email', strtolower((string) $request->input('email')))->first();
                if (! $tenant || ! $user || ! Hash::check((string) $request->input('password'), $user->password) || ! $user->active) {
                    return null;
                }

                return DB::connection('pgsql')->table('memberships')->where('tenant_id', $tenant->id)->where('user_id', $user->id)->where('active', true)->exists() ? $user : null;
            }, 200000);
        });
        RateLimiter::for('workspace-login', fn (Request $request) => Limit::perMinute(5)->by(hash('sha256', $request->getHost().'|'.strtolower((string) $request->input('email')).'|'.$request->ip())));
        RateLimiter::for('workspace-mfa', fn (Request $request) => Limit::perMinute(5)->by($request->getHost().'|'.$request->session()->get('login.id').'|'.$request->ip()));
        Event::listen(Login::class, function (Login $event) {
            $tenant = request()->attributes->get('tenant');
            if (! $tenant || ! $event->user instanceof User) {
                return;
            }
            request()->session()->put(['tenant_login_id' => $tenant->id, 'identity_version' => $event->user->session_version]);
            request()->session()->forget(['challenge.tenant_id', 'challenge.version']);
            app(Audit::class)->platform('identity.login', $event->user->id, $tenant->id, $event->user->id);
        });
        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user instanceof User) {
                app(Audit::class)->platform('identity.logout', (string) $event->user->getAuthIdentifier(), request()->attributes->get('tenant')?->id);
            }
        });
        Event::listen(Failed::class, fn () => app(Audit::class)->platform('identity.failed', null, request()->attributes->get('tenant')?->id));
        Event::listen(TwoFactorAuthenticationChallenged::class, function ($event) {
            request()->session()->put(['challenge.tenant_id' => request()->attributes->get('tenant')->id, 'challenge.version' => $event->user->session_version]);
        });
        foreach ([TwoFactorAuthenticationConfirmed::class, TwoFactorAuthenticationDisabled::class] as $eventClass) {
            Event::listen($eventClass, function ($event) {
                $event->user->increment('session_version');
                request()->session()->put('identity_version', $event->user->session_version);
                app(Audit::class)->platform('identity.revoked', $event->user->id, request()->attributes->get('tenant')?->id, $event->user->id);
            });
        }
        foreach ([
            TwoFactorAuthenticationEnabled::class => 'identity.mfa_setup_started',
            TwoFactorAuthenticationConfirmed::class => 'identity.mfa_confirmed',
            TwoFactorAuthenticationDisabled::class => 'identity.mfa_disabled',
            RecoveryCodesGenerated::class => 'identity.recovery_codes_generated',
            RecoveryCodeReplaced::class => 'identity.recovery_code_used',
        ] as $eventClass => $action) {
            Event::listen($eventClass, fn ($event) => app(Audit::class)->platform($action, $event->user->id, request()->attributes->get('tenant')?->id, $event->user->id));
        }
    }
}
