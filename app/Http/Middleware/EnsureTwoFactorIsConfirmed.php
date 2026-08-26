<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks the app until a pending 2FA challenge is answered. The login flow
 * stores the candidate id in the session instead of logging the user in, so
 * this only guards the narrow window where a challenge is outstanding.
 */
class EnsureTwoFactorIsConfirmed
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->has('2fa:user:id')) {
            return redirect()->route('two-factor.challenge');
        }

        if ($request->session()->has('2fa:superadmin:id')) {
            return redirect()->route('super-admin.two-factor.challenge');
        }

        return $next($request);
    }
}
