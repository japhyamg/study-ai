<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role gate — mirrors the original app's role scoping (super_admin|admin|teacher|student).
 * Usage: ->middleware('role:teacher') or ->middleware('role:admin,super_admin')
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $highest = $this->highestRole($user);
        if ($highest === null) {
            abort(403, 'You are not a member of any school.');
        }

        // super_admin can do anything
        if ($highest === \App\Models\SchoolMember::ROLE_SUPER_ADMIN) {
            return $next($request);
        }

        if (! in_array($highest, $roles, true)) {
            abort(403, 'Insufficient role privileges.');
        }

        return $next($request);
    }

    /**
     * Resolve the active role for the user.
     * Priority: super_admin > admin > teacher > student.
     */
    protected function highestRole(\App\Models\User $user): ?string
    {
        $order = [
            \App\Models\SchoolMember::ROLE_SUPER_ADMIN,
            \App\Models\SchoolMember::ROLE_ADMIN,
            \App\Models\SchoolMember::ROLE_TEACHER,
            \App\Models\SchoolMember::ROLE_STUDENT,
        ];
        $roles = $user->memberships()->pluck('role')->unique()->all();
        foreach ($order as $r) {
            if (in_array($r, $roles, true)) {
                return $r;
            }
        }
        return null;
    }
}
