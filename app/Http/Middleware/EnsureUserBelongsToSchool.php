<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hard tenant isolation: a signed-in user may only act inside the school whose
 * subdomain they are on. Prevents horizontal access across tenants even if a
 * session cookie leaks across subdomains.
 */
class EnsureUserBelongsToSchool
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenant = app()->bound('tenant') ? app('tenant') : null;

        if (! $user || ! $tenant) {
            return $next($request);
        }

        if (! $user->belongsToSchool($tenant->id)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Your account does not belong to '.$tenant->name.'.',
            ]);
        }

        if (! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Your account has been deactivated. Please contact your school administrator.',
            ]);
        }

        return $next($request);
    }
}
