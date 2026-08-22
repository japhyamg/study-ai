<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'StudyAI') }} — {{ $title ?? '' }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fraunces:400,500,560,600|geist:400,500,600&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- KaTeX for math rendering in study materials --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/contrib/auto-render.min.js"></script>
    <script defer>
      document.addEventListener('DOMContentLoaded', function () {
        if (window.renderMathInElement) {
          renderMathInElement(document.body, {
            delimiters: [
              {left: '$$', right: '$$', display: true},
              {left: '$', right: '$', display: false},
              {left: '\\(', right: '\\)', display: false},
              {left: '\\[', right: '\\]', display: true}
            ],
            ignoredTags: ['script', 'noscript', 'style', 'textarea', 'pre', 'code']
          });
        }
      });
    </script>
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
<div class="app-shell">

    {{-- Sidebar --}}
    <aside class="app-side">
        <div class="px-5 py-4" style="border-bottom:1px solid var(--line)">
            <div class="font-display text-xl" style="color:var(--ink)">Study<span style="color:var(--accent)">AI</span></div>
            <div class="text-xs faint mt-1">{{ auth()->user()?->currentSchool()?->name ?? 'No school' }}</div>
        </div>

        <nav class="flex-1 px-3 py-4" style="overflow-y:auto">
            @if(auth()->user()?->isSuperAdmin())
                <div class="nav-section">Platform</div>
                <a href="{{ route('super-admin.dashboard') }}" class="nav-link {{ Route::is('super-admin.dashboard') ? 'nav-link-active' : '' }}">Dashboard</a>
                <a href="{{ route('super-admin.schools') }}" class="nav-link {{ Route::is('super-admin.schools*') ? 'nav-link-active' : '' }}">Schools</a>
                <a href="{{ route('super-admin.ai-providers') }}" class="nav-link {{ Route::is('super-admin.ai-providers*') ? 'nav-link-active' : '' }}">AI Providers</a>
                <a href="{{ route('super-admin.token-limits') }}" class="nav-link {{ Route::is('super-admin.token-limits*') ? 'nav-link-active' : '' }}">Token Limits</a>
                <a href="{{ route('super-admin.token-usage') }}" class="nav-link {{ Route::is('super-admin.token-usage*') ? 'nav-link-active' : '' }}">Token Usage</a>
            @endif

            @if(auth()->user()?->isAdmin())
                <div class="nav-section">School</div>
                <a href="{{ route('admin.dashboard') }}" class="nav-link {{ Route::is('admin.dashboard') ? 'nav-link-active' : '' }}">Dashboard</a>
                <a href="{{ route('admin.analytics') }}" class="nav-link {{ Route::is('admin.analytics') ? 'nav-link-active' : '' }}">Analytics</a>
                <a href="{{ route('admin.classes.index') }}" class="nav-link {{ Route::is('admin.classes*') ? 'nav-link-active' : '' }}">Classes</a>
                <a href="{{ route('admin.members') }}" class="nav-link {{ Route::is('admin.members*') ? 'nav-link-active' : '' }}">Members</a>
                <a href="{{ route('admin.subjects.index') }}" class="nav-link {{ Route::is('admin.subjects*') ? 'nav-link-active' : '' }}">Subjects</a>
                <a href="{{ route('admin.terms.index') }}" class="nav-link {{ Route::is('admin.terms*') ? 'nav-link-active' : '' }}">Terms</a>
                <a href="{{ route('admin.settings') }}" class="nav-link {{ Route::is('admin.settings*') ? 'nav-link-active' : '' }}">Settings</a>
            @endif

            @if(auth()->user()?->isTeacher())
                <div class="nav-section">Teaching</div>
                <a href="{{ route('teacher.dashboard') }}" class="nav-link {{ Route::is('teacher.dashboard') ? 'nav-link-active' : '' }}">Dashboard</a>
                <a href="{{ route('teacher.classes.index') }}" class="nav-link {{ Route::is('teacher.classes*') ? 'nav-link-active' : '' }}">Classes</a>
                <a href="{{ route('teacher.exams.index') }}" class="nav-link {{ Route::is('teacher.exams*') ? 'nav-link-active' : '' }}">Exams</a>
                <a href="{{ route('teacher.materials.index') }}" class="nav-link {{ Route::is('teacher.materials*') ? 'nav-link-active' : '' }}">Materials</a>
                <a href="{{ route('teacher.question-bank.index') }}" class="nav-link {{ Route::is('teacher.question-bank*') ? 'nav-link-active' : '' }}">Question Bank</a>
            @endif

            @if(auth()->user()?->isStudent())
                <div class="nav-section">Learn</div>
                <a href="{{ route('student.dashboard') }}" class="nav-link {{ Route::is('student.dashboard') ? 'nav-link-active' : '' }}">Dashboard</a>
                <a href="{{ route('student.exams') }}" class="nav-link {{ Route::is('student.exams*') ? 'nav-link-active' : '' }}">Exams</a>
                <a href="{{ route('student.materials') }}" class="nav-link {{ Route::is('student.materials*') ? 'nav-link-active' : '' }}">Materials</a>
                <a href="{{ route('student.flashcards') }}" class="nav-link {{ Route::is('student.flashcards') ? 'nav-link-active' : '' }}">Flashcards</a>
                <a href="{{ route('student.study.index') }}" class="nav-link {{ Route::is('student.study*') ? 'nav-link-active' : '' }}">Study</a>
                <a href="{{ route('student.topics.index') }}" class="nav-link {{ Route::is('student.topics*') ? 'nav-link-active' : '' }}">Topics</a>
            @endif
        </nav>

        <div class="px-3 py-3" style="border-top:1px solid var(--line)">
            <div class="px-3 py-2 text-xs" style="color:var(--ink-soft)">
                <div class="font-medium" style="color:var(--ink)">{{ auth()->user()?->name }}</div>
                <div class="faint text-[11px] uppercase tracking-wide">{{ str_replace('_',' ', auth()->user()?->highestRole() ?? '') }}</div>
            </div>
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="nav-link w-full" type="submit">Log out</button>
            </form>
        </div>
    </aside>

    {{-- Content --}}
    <div class="app-content">
        <header class="app-top">
            <h1 class="font-display text-xl" style="color:var(--ink)">{{ $title ?? 'Dashboard' }}</h1>
            <div class="flex items-center gap-2">
                <x-ui.theme-toggle />
                @isset($actions)<div class="flex items-center gap-2">{{ $actions }}</div>@endisset
            </div>
        </header>
        <main class="app-main">
            @if (session('status'))
                <div class="alert alert-ok mb-5">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger mb-5">{{ session('error') }}</div>
            @endif
            @if (isset($errors) && $errors?->any())
                <div class="alert alert-danger mb-5">
                    <ul class="list-disc pl-5 text-sm">@foreach ($errors?->all() ?? [] as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            {{ $slot }}
        </main>
    </div>
</div>
@stack('scripts')
</body>
</html>
