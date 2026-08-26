<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Tenancy\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role gate — super_admin | admin | teacher | student.
 * Usage: ->middleware('role:teacher') or ->middleware('role:teacher,admin,super_admin')
 *
 * Roles are resolved from the per-type profile tables (platform_admins,
 * school_admins, teachers, students) via User::highestRole().
 *
 * When the request is served from a school subdomain, the user must also be a
 * member of that school (platform admins are always allowed).
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $highest = $user->highestRole();

        if ($highest === null) {
            abort(403, 'You are not a member of any school yet.');
        }

        // Platform admins can do anything, anywhere.
        if ($highest === User::ROLE_SUPER_ADMIN) {
            return $next($request);
        }

        if (! in_array($highest, $roles, true)) {
            abort(403, 'Insufficient role privileges.');
        }

        // Tenant scope: on a school subdomain you must belong to that school.
        $tenant = Tenant::school();
        if ($tenant && ! $user->belongsToSchool($tenant->id)) {
            abort(403, "You are not a member of {$tenant->name}.");
        }

        return $next($request);
    }
}
