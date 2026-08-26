<?php

namespace App\Support\Tenancy;

use App\Models\School;

/**
 * Request-scoped tenant holder.
 *
 * Populated by App\Http\Middleware\IdentifyTenant on every web request:
 *  - Main (central) domain  → Tenant::school() is null  (super-admin / platform pages)
 *  - {slug}.domain.tld      → Tenant::school() is the matching School (a school workspace)
 *
 * In local development (localhost / 127.0.0.1 / *.test without a subdomain) the host is
 * treated as central and the tenant falls back to the authenticated user's own school,
 * so the whole app keeps working without real DNS subdomains.
 */
class Tenant
{
    protected static ?School $school = null;
    protected static bool $central = true;

    public static function setSchool(?School $school): void
    {
        static::$school = $school;
        static::$central = $school === null;
    }

    public static function school(): ?School
    {
        return static::$school;
    }

    public static function isCentral(): bool
    {
        return static::$central;
    }

    public static function flush(): void
    {
        static::$school = null;
        static::$central = true;
    }
}
