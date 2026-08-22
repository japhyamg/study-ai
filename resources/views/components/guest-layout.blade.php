<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'StudyAI') }} — {{ $title ?? '' }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fraunces:400,500,560,600|geist:400,500,600&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body>
    <div class="min-h-screen flex items-center justify-center px-4" style="background:var(--paper)">
        <div class="w-full max-w-sm">
            <div class="text-center mb-6">
                <div class="font-display text-2xl" style="color:var(--ink)">Study<span style="color:var(--accent)">AI</span></div>
                <div class="faint text-sm mt-1">{{ $title ?? 'Welcome' }}</div>
            </div>
            <div class="surface p-6">
                {{ $slot }}
            </div>
            <div class="text-center faint text-xs mt-4">{{ $footer ?? '' }}</div>
        </div>
    </div>
    @stack('scripts')
</body>
</html>
