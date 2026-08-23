<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role gate, scoped to the active tenant.
 *
 * Usage: ->middleware('role:teacher') or ->middleware('role:admin,teacher')
 *
 * Note super-admins are NOT implicitly granted school roles any more — they
 * live on their own guard and their own domain. To act inside a school they
 * must use the explicit impersonation flow, which issues a real `web` session.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->guest(route('login'));
        }

        $role = $user->roleInSchool();

        if ($role === null) {
            return redirect()->route('onboarding');
        }

        if (! in_array($role, $roles, true)) {
            abort(403, 'You do not have access to this area.');
        }

        return $next($request);
    }
}
