<?php

namespace App\Http\Middleware;

use App\Models\School;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active tenant (school) for the request and binds it to the
 * container as `tenant`, plus the `School` class for injection.
 *
 * Resolution order:
 *   1. Vanity domain            — lincoln.edu.ng
 *   2. Subdomain                — lincoln.studyai.test
 *   3. `?tenant=` query param   — dev/preview fallback (persisted to session)
 *   4. Session                  — sticky across the fallback's later requests
 *
 * A missing or suspended tenant is handled explicitly rather than 404-ing into
 * a confusing state.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $school = $this->resolve($request);

        if (! $school) {
            return $this->unknownTenant($request);
        }

        if ($school->isSuspended()) {
            return response()->view('errors.tenant-suspended', ['school' => $school], 503);
        }

        app()->instance('tenant', $school);
        app()->instance(School::class, $school);
        view()->share('tenant', $school);

        // Keep the fallback parameter on every generated URL so links stay
        // inside the tenant when wildcard DNS isn't available.
        if ($this->usingFallback($request)) {
            session(['tenant_subdomain' => $school->subdomain]);
            URL::defaults(['tenant' => $school->subdomain]);
        }

        return $next($request);
    }

    protected function resolve(Request $request): ?School
    {
        $host = strtolower($request->getHost());

        // 1. Vanity domain
        if ($school = School::where('domain', $host)->first()) {
            return $school;
        }

        // 2. Subdomain of the configured apex domain
        if ($label = $this->subdomainLabel($host)) {
            if ($school = School::where('subdomain', $label)->first()) {
                return $school;
            }
        }

        // 3 & 4. Dev/preview fallback
        if (config('tenancy.path_fallback')) {
            $slug = $request->query('tenant') ?: session('tenant_subdomain');

            if ($slug) {
                return School::where('subdomain', $slug)->first();
            }

            // Single-tenant convenience: if only one school exists, use it.
            if (School::count() === 1) {
                return School::first();
            }
        }

        return null;
    }

    /** Extract the tenant label from the host, or null if this is a central host. */
    protected function subdomainLabel(string $host): ?string
    {
        $base = strtolower((string) config('tenancy.domain'));

        if (! $base || $host === $base || ! str_ends_with($host, '.'.$base)) {
            return null;
        }

        $label = substr($host, 0, -(strlen($base) + 1));

        // Only single-level labels are tenants; ignore www and the central host.
        if ($label === '' || str_contains($label, '.')) {
            return null;
        }

        if (in_array($label, ['www', config('tenancy.central_subdomain')], true)) {
            return null;
        }

        return $label;
    }

    protected function usingFallback(Request $request): bool
    {
        return config('tenancy.path_fallback')
            && ! $this->subdomainLabel(strtolower($request->getHost()));
    }

    protected function unknownTenant(Request $request): Response
    {
        if ($request->expectsJson()) {
            abort(404, 'Unknown school.');
        }

        return response()->view('errors.tenant-not-found', [
            'host' => $request->getHost(),
            'schools' => config('tenancy.path_fallback')
                ? School::active()->orderBy('name')->limit(25)->get()
                : collect(),
        ], 404);
    }
}
