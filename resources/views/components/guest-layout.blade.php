<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Welcome' }} · {{ config('app.name', 'StudyAI') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=geist:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
    <script>
      (function () {
        var theme = localStorage.getItem('theme');
        var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        var dark = theme === 'dark' || (theme === null && prefersDark);
        if (dark) document.documentElement.classList.add('dark');
      })();
    </script>
</head>
<body>
@php($school = \App\Support\Tenancy\Tenant::school())
@php($pageTitle = $attributes->get('title') ?? 'Welcome')
@php($pageFooter = $attributes->get('footer') ?? '')
<div class="min-h-screen flex items-center justify-center px-4 py-10" style="background:var(--paper)">
    <div class="w-full max-w-sm">
        <div class="text-center mb-6">
            <span class="inline-flex items-center justify-center rounded-lg text-white font-semibold mb-3"
                  style="width:2.5rem;height:2.5rem;background:var(--accent);font-size:1.1rem">S</span>
            <div class="text-xl font-semibold" style="color:var(--ink)">Study<span style="color:var(--accent)">AI</span></div>
            @if($school)
                <div class="text-sm muted mt-1">Signing in to <span class="font-medium text-ink">{{ $school->name }}</span></div>
            @else
                <div class="text-sm faint mt-1">{{ $pageTitle }}</div>
            @endif
        </div>
        <div class="surface p-6 sm:p-7">
            {{ $slot }}
        </div>
        <div class="text-center faint text-xs mt-5">{{ $pageFooter }}</div>
    </div>
</div>
@stack('scripts')
</body>
</html>
