<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Base application domain
    |--------------------------------------------------------------------------
    |
    | The apex domain schools live under. A school with subdomain "lincoln"
    | is served at lincoln.{domain}. Leave APP_DOMAIN unset locally to
    | automatically fall back to ?tenant= resolution.
    |
    */

    'domain' => env('APP_DOMAIN'),

    'scheme' => env('APP_SCHEME', 'https'),

    /*
    |--------------------------------------------------------------------------
    | Super-admin (platform) host
    |--------------------------------------------------------------------------
    |
    | The host the SaaS operator signs in on. Anything else is treated as a
    | tenant host. Defaults to admin.{domain}.
    |
    */

    'central_subdomain' => env('CENTRAL_SUBDOMAIN', 'admin'),

    /*
    |--------------------------------------------------------------------------
    | Reserved subdomains
    |--------------------------------------------------------------------------
    |
    | Labels a school may never claim, because they collide with platform
    | hosts or common infrastructure names.
    |
    */

    'reserved' => [
        'admin', 'www', 'app', 'api', 'mail', 'smtp', 'imap', 'ftp', 'ns1', 'ns2',
        'cdn', 'assets', 'static', 'status', 'support', 'help', 'docs', 'blog',
        'billing', 'account', 'accounts', 'auth', 'login', 'register', 'dashboard',
        'super', 'superadmin', 'super-admin', 'platform', 'system', 'root', 'test',
        'staging', 'dev', 'demo', 'preview', 'localhost',
    ],

    /*
    |--------------------------------------------------------------------------
    | Path / query fallback
    |--------------------------------------------------------------------------
    |
    | When true the tenant may also be resolved from a `?tenant=` query
    | parameter (persisted in the session). Required for local development and
    | sandboxed previews where wildcard DNS is unavailable.
    |
    */

    'path_fallback' => (bool) env('TENANCY_PATH_FALLBACK', env('APP_ENV') !== 'production'),

];
