<?php

namespace App\Http\Middleware;

use App\Models\School;
use App\Support\Tenancy\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the current tenant from the request host.
 *
 *  - Host IS a central domain            → central context (super-admin / platform).
 *  - Host is {slug}.{central-domain}     → tenant context for the matching School.
 *  - Anything else (localhost, IPs, …)   → central context (local development),
 *                                          where the school is resolved per-user
 *                                          from the authenticated user's profiles.
 */
class IdentifyTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        Tenant::flush();

        $host = $this->normaliseHost($request->getHost());
        $centralDomains = config('tenancy.central_domains');

        // ── Central domain (main domain) ──
        if (in_array($host, $centralDomains, true)) {
            Tenant::setSchool(null);

            return $next($request);
        }

        // ── School subdomain: {slug}.{central-domain} ──
        foreach ($centralDomains as $domain) {
            if (! str_ends_with($host, '.'.$domain)) {
                continue;
            }

            $slug = substr($host, 0, -strlen($domain) - 1);

            if ($slug === '' || in_array($slug, config('tenancy.reserved_slugs'), true)) {
                Tenant::setSchool(null);

                return $next($request);
            }

            $school = School::where('slug', $slug)->first();

            if (! $school) {
                abort(404, 'No school exists at this address.');
            }

            Tenant::setSchool($school);

            // A logged-in user that does not belong to this school cannot stay here.
            $user = $request->user();
            if ($user && ! $user->isSuperAdmin() && ! $user->belongsToSchool($school->id)) {
                auth()->guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->withErrors(['email' => "This account is not a member of {$school->name}."]);
            }

            session(['active_school_id' => $school->id]);

            return $next($request);
        }

        // ── Unknown host (local dev / custom setups) → central ──
        Tenant::setSchool(null);

        return $next($request);
    }

    protected function normaliseHost(string $host): string
    {
        return strtolower(trim($host));
    }
}
