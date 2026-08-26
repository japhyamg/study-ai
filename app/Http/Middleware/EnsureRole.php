<?php

namespace App\Http\Middleware;

use App\Models\SchoolMember;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role gate, scoped to the active tenant.
 *
 * Usage: ->middleware('role:teacher') or ->middleware('role:admin,teacher')
 *
 * Super-admins live on their own guard and their own table, and are not
 * members of any school. They satisfy only the `super_admin` role here; to act
 * inside a school they must use the explicit impersonation flow, which issues
 * a real `web` session as a real school user.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // Platform staff authenticate on a different guard, so check it first
        // — request->user() would return null for them on tenant routes and
        // the school user on platform routes is never the right principal.
        if ($superAdmin = $request->user('superadmin')) {
            if (! in_array(SchoolMember::ROLE_SUPER_ADMIN, $roles, true)) {
                abort(403, 'This area belongs to a school, not the platform.');
            }

            return $next($request);
        }

        $user = $request->user();

        if (! $user) {
            return redirect()->guest(route('login'));
        }

        // A school user can never satisfy the platform role.
        if ($roles === [SchoolMember::ROLE_SUPER_ADMIN]) {
            abort(403, 'You do not have access to this area.');
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
