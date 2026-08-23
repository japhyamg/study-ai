<?php

use App\Exceptions\AiServiceException;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureTwoFactorIsConfirmed;
use App\Http\Middleware\EnsureUserBelongsToSchool;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            $domain = config('tenancy.domain');
            $central = config('tenancy.central_subdomain', 'admin');

            // ── Platform (super-admin) — the central domain ──
            // With wildcard DNS this is admin.{domain}; without it (local /
            // preview) the routes stay reachable on the default host.
            $superAdmin = Route::middleware('web')->name('super-admin.');

            if ($domain) {
                $superAdmin = $superAdmin->domain($central.'.'.$domain);
            }

            $superAdmin->prefix($domain ? '' : 'super-admin')
                ->group(base_path('routes/superadmin.php'));

            // ── Marketing / public pages on the apex domain ──
            $public = Route::middleware('web');

            if ($domain) {
                $public = $public->domain($domain);
            }

            $public->group(base_path('routes/public.php'));

            // ── Tenant application — {school}.{domain} ──
            $tenant = Route::middleware(['web', 'tenant']);

            if ($domain) {
                $tenant = $tenant->domain('{school}.'.$domain);
            }

            $tenant->group(base_path('routes/web.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind a load balancer / preview proxy, honour X-Forwarded-* so that
        // generated URLs keep the correct scheme and host.
        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES', '*') === '*' ? '*' : explode(',', (string) env('TRUSTED_PROXIES')),
        );

        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'school.user' => EnsureUserBelongsToSchool::class,
            'guest' => RedirectIfAuthenticated::class,
            'role' => EnsureRole::class,
            '2fa' => EnsureTwoFactorIsConfirmed::class,
        ]);

        // Tenant resolution must run before anything reads the active school.
        $middleware->priority([
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            ResolveTenant::class,
            \Illuminate\Auth\Middleware\Authenticate::class,
            EnsureUserBelongsToSchool::class,
            EnsureTwoFactorIsConfirmed::class,
            EnsureRole::class,
            \Illuminate\Auth\Middleware\Authorize::class,
        ]);

        // Unauthenticated users are sent to the login route of the host they
        // are already on, so a super-admin never lands on a school login form.
        $middleware->redirectGuestsTo(function (\Illuminate\Http\Request $request) {
            return $request->routeIs('super-admin.*')
                ? route('super-admin.login')
                : route('login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Give every reported exception a short reference and attach it to the
        // log line. The same code is shown on the error page, so a user can
        // quote six characters instead of pasting a stack trace — and we can
        // find the exact entry without guessing from a timestamp.
        $exceptions->context(fn () => ['reference' => app('error.reference')]);

        // AI failures are already translated where they are caught. This is the
        // safety net for anything that escapes to an HTTP response: log the
        // detail, show the user plain language.
        $exceptions->render(function (AiServiceException $e, \Illuminate\Http\Request $request) {
            Log::error('AI request failed', [
                'reference' => $e->reference(),
                'detail' => $e->privateDetail(),
            ]);

            $payload = ['message' => $e->publicMessage(), 'reference' => $e->reference()];

            return $request->expectsJson()
                ? response()->json($payload, 503)
                : back()->with('error', $e->publicMessage().' (ref '.$e->reference().')');
        });
    })->create();
