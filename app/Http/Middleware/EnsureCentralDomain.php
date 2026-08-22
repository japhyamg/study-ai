<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps platform (super-admin) pages on the main domain only.
 *
 * On a school subdomain the user is redirected to the same path on the
 * central domain. When no central domain is configured (local development)
 * the request is allowed through as-is.
 */
class EnsureCentralDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Tenant::school()) {
            return $next($request);
        }

        $centralUrl = rtrim(config('app.url'), '/');
        $centralHost = parse_url($centralUrl, PHP_URL_HOST);

        // Local development — no real central domain to bounce to.
        if (! $centralHost
            || in_array($centralHost, ['localhost', '127.0.0.1', '0.0.0.0', '[::1]', '::1'], true)
            || ! in_array($centralHost, config('tenancy.central_domains'), true)) {
            return $next($request);
        }

        return redirect()->away($centralUrl.$request->path());
    }
}
