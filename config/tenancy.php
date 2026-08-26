<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Central (main) domains
    |--------------------------------------------------------------------------
    |
    | Hosts that serve the PLATFORM app (super-admin, marketing, sign-in).
    | Every other host is expected to be `{school-slug}.{central-domain}` and is
    | resolved to a School tenant by App\Http\Middleware\IdentifyTenant.
    |
    | Example — with APP_CENTRAL_DOMAINS=studyai.test:
    |     https://studyai.test          → central   (super-admin)
    |     https://demo.studyai.test     → tenant    (school with slug "demo")
    |
    | Separate multiple domains with commas: "studyai.com,studyai.eu".
    | Leave empty in local development — requests to localhost / 127.0.0.1
    | are treated as central and the app stays fully path-based.
    |
    */

    'central_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('APP_CENTRAL_DOMAINS', ''))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Reserved subdomains
    |--------------------------------------------------------------------------
    |
    | Subdomains that never map to a school (they behave like the central
    | domain). Schools cannot be created with these slugs.
    |
    */

    'reserved_slugs' => [
        'www', 'app', 'api', 'admin', 'platform', 'mail', 'smtp', 'cdn', 'static',
        'auth', 'login', 'support', 'help', 'blog', 'docs', 'status',
    ],

];
