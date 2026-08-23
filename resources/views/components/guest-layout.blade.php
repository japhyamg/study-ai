@php
    $school = $tenant ?? null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Sign in' }} · {{ $school?->name ?? config('app.name', 'StudyAI') }}</title>

    <script>
        (function () {
            try {
                var t = localStorage.getItem('theme');
                if (t === 'dark' || (!t && matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dark');
                }
            } catch (e) {}
        })();
    </script>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600|plus-jakarta-sans:500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="h-full">

<div class="flex min-h-dvh flex-col lg:flex-row">

    {{-- Brand panel — desktop only, keeps the form uncluttered on mobile --}}
    <aside class="relative hidden w-[44%] max-w-xl flex-col justify-between border-e bg-surface p-10 lg:flex">
        <div class="flex items-center gap-2.5">
            <span class="grid h-9 w-9 place-items-center rounded-lg bg-brand-600 text-sm font-bold text-white">
                {{ $school?->initials() ?? 'S' }}
            </span>
            <span class="text-sm font-semibold text-ink">
                {{ $school?->name ?? config('app.name', 'StudyAI') }}
            </span>
        </div>

        <div class="max-w-sm">
            <h2 class="text-2xl font-semibold leading-snug tracking-tight text-ink">
                {{ $pitchTitle ?? 'Teaching, learning and assessment in one place.' }}
            </h2>
            <p class="mt-3 text-sm leading-relaxed text-muted">
                {{ $pitchBody ?? 'Plan lessons, publish study material, run assessments and follow every student\'s progress — without the busywork.' }}
            </p>

            <ul class="mt-6 space-y-2.5 text-sm text-muted">
                @foreach (($pitchPoints ?? ['AI-assisted study material', 'Exams with instant marking', 'Progress you can act on']) as $point)
                    <li class="flex items-start gap-2">
                        <span class="mt-0.5 grid h-4 w-4 flex-none place-items-center rounded-full bg-brand-600/10 text-brand-600">
                            <x-icon name="check" class="h-3 w-3" stroke-width="2.5" />
                        </span>
                        {{ $point }}
                    </li>
                @endforeach
            </ul>
        </div>

        <p class="text-xs text-faint">
            &copy; {{ date('Y') }} {{ config('app.name', 'StudyAI') }}
        </p>
    </aside>

    {{-- Form panel --}}
    <main class="flex flex-1 flex-col justify-center px-4 py-10 sm:px-8">
        <div class="mx-auto w-full max-w-sm">

            {{-- Mobile brand --}}
            <div class="mb-7 flex items-center gap-2.5 lg:hidden">
                <span class="grid h-9 w-9 place-items-center rounded-lg bg-brand-600 text-sm font-bold text-white">
                    {{ $school?->initials() ?? 'S' }}
                </span>
                <span class="text-sm font-semibold text-ink">
                    {{ $school?->name ?? config('app.name', 'StudyAI') }}
                </span>
            </div>

            <header class="mb-6">
                <h1 class="text-xl font-semibold tracking-tight text-ink">{{ $title ?? 'Sign in' }}</h1>
                @isset($description)
                    <p class="mt-1 text-sm text-muted">{{ $description }}</p>
                @endisset
            </header>

            @if (session('status'))
                <div class="alert alert-ok mb-4" role="status">
                    <x-icon name="check-circle" class="mt-px flex-none" />
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            {{ $slot }}

            @isset($footer)
                <div class="mt-6 text-center text-sm text-muted">{{ $footer }}</div>
            @endisset
        </div>
    </main>
</div>

@stack('scripts')
</body>
</html>
