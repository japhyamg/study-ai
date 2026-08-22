<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Dashboard' }} · {{ config('app.name', 'StudyAI') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=geist:400,500,600,700&display=swap" rel="stylesheet" />
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
@php($authUser = auth()->user())
<div class="app-shell" x-data="{ sidebarOpen: false }" @resize.window="if (window.innerWidth >= 1024) sidebarOpen = false">

    {{-- Mobile backdrop --}}
    <div class="app-backdrop" :class="sidebarOpen ? 'open' : ''" @click="sidebarOpen = false" x-cloak aria-hidden="true"></div>

    {{-- ── Sidebar ── --}}
    <aside class="app-side" :class="sidebarOpen ? 'open' : ''">
        <div class="px-5 pt-5 pb-4 flex items-center justify-between" style="border-bottom:1px solid var(--line)">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 min-w-0">
                <span class="flex items-center justify-center rounded-lg text-white font-semibold"
                      style="width:1.75rem;height:1.75rem;background:var(--accent);font-size:.9rem;flex:none">S</span>
                <span class="font-semibold text-[1.05rem] leading-none" style="color:var(--ink)">Study<span style="color:var(--accent)">AI</span></span>
            </a>
            {{-- Mobile close button --}}
            <button class="lg:hidden text-faint hover:text-ink p-1" @click="sidebarOpen = false" aria-label="Close menu">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>

        @php($currentSchool = $authUser?->currentSchool())
        <div class="px-5 pt-3 pb-1">
            @if($currentSchool)
                <div class="text-[.7rem] uppercase tracking-wider text-faint">School workspace</div>
                <div class="text-sm font-medium truncate" style="color:var(--ink)">{{ $currentSchool->name }}</div>
            @elseif($authUser?->isSuperAdmin())
                <div class="text-[.7rem] uppercase tracking-wider text-faint">Platform</div>
                <div class="text-sm font-medium truncate" style="color:var(--ink)">Main domain · Administration</div>
            @endif
        </div>

        <nav class="flex-1 px-3 py-2" style="overflow-y:auto">
            @if($authUser?->isSuperAdmin())
                <div class="nav-section">Platform</div>
                <a href="{{ route('super-admin.dashboard') }}" class="nav-link {{ Route::is('super-admin.dashboard') ? 'nav-link-active' : '' }}"><x-ui.icon name="home"/>Dashboard</a>
                <a href="{{ route('super-admin.schools') }}" class="nav-link {{ Route::is('super-admin.schools*') ? 'nav-link-active' : '' }}"><x-ui.icon name="building"/>Schools</a>
                <a href="{{ route('super-admin.ai-providers') }}" class="nav-link {{ Route::is('super-admin.ai-providers*') ? 'nav-link-active' : '' }}"><x-ui.icon name="cpu"/>AI Providers</a>
                <a href="{{ route('super-admin.token-limits') }}" class="nav-link {{ Route::is('super-admin.token-limits*') ? 'nav-link-active' : '' }}"><x-ui.icon name="sliders"/>Token Limits</a>
                <a href="{{ route('super-admin.token-usage') }}" class="nav-link {{ Route::is('super-admin.token-usage*') ? 'nav-link-active' : '' }}"><x-ui.icon name="chart"/>Token Usage</a>
            @endif

            @if($authUser?->isAdmin())
                <div class="nav-section">School</div>
                <a href="{{ route('admin.dashboard') }}" class="nav-link {{ Route::is('admin.dashboard') ? 'nav-link-active' : '' }}"><x-ui.icon name="home"/>Dashboard</a>
                <a href="{{ route('admin.analytics') }}" class="nav-link {{ Route::is('admin.analytics*') ? 'nav-link-active' : '' }}"><x-ui.icon name="trend"/>Analytics</a>
                <a href="{{ route('admin.classes.index') }}" class="nav-link {{ Route::is('admin.classes*') ? 'nav-link-active' : '' }}"><x-ui.icon name="book"/>Classes</a>
                <a href="{{ route('admin.members') }}" class="nav-link {{ Route::is('admin.members*') ? 'nav-link-active' : '' }}"><x-ui.icon name="users"/>Members</a>
                <a href="{{ route('admin.subjects.index') }}" class="nav-link {{ Route::is('admin.subjects*') ? 'nav-link-active' : '' }}"><x-ui.icon name="tag"/>Subjects</a>
                <a href="{{ route('admin.terms.index') }}" class="nav-link {{ Route::is('admin.terms*') ? 'nav-link-active' : '' }}"><x-ui.icon name="calendar"/>Terms</a>
                <a href="{{ route('admin.settings') }}" class="nav-link {{ Route::is('admin.settings*') ? 'nav-link-active' : '' }}"><x-ui.icon name="settings"/>Settings</a>
            @endif

            @if($authUser?->isTeacher())
                <div class="nav-section">Teaching</div>
                <a href="{{ route('teacher.dashboard') }}" class="nav-link {{ Route::is('teacher.dashboard') ? 'nav-link-active' : '' }}"><x-ui.icon name="home"/>Dashboard</a>
                <a href="{{ route('teacher.classes.index') }}" class="nav-link {{ Route::is('teacher.classes*') ? 'nav-link-active' : '' }}"><x-ui.icon name="book"/>Classes</a>
                <a href="{{ route('teacher.exams.index') }}" class="nav-link {{ Route::is('teacher.exams*') ? 'nav-link-active' : '' }}"><x-ui.icon name="clipboard"/>Exams</a>
                <a href="{{ route('teacher.materials.index') }}" class="nav-link {{ Route::is('teacher.materials*') ? 'nav-link-active' : '' }}"><x-ui.icon name="file"/>Materials</a>
                <a href="{{ route('teacher.question-bank.index') }}" class="nav-link {{ Route::is('teacher.question-bank*') ? 'nav-link-active' : '' }}"><x-ui.icon name="layers"/>Question Bank</a>
            @endif

            @if($authUser?->isStudent())
                <div class="nav-section">Learn</div>
                <a href="{{ route('student.dashboard') }}" class="nav-link {{ Route::is('student.dashboard') ? 'nav-link-active' : '' }}"><x-ui.icon name="home"/>Dashboard</a>
                <a href="{{ route('student.exams') }}" class="nav-link {{ Route::is('student.exams*') ? 'nav-link-active' : '' }}"><x-ui.icon name="clipboard"/>Exams</a>
                <a href="{{ route('student.materials') }}" class="nav-link {{ Route::is('student.materials*') ? 'nav-link-active' : '' }}"><x-ui.icon name="file"/>Materials</a>
                <a href="{{ route('student.flashcards') }}" class="nav-link {{ Route::is('student.flashcards*') ? 'nav-link-active' : '' }}"><x-ui.icon name="cards"/>Flashcards</a>
                <a href="{{ route('student.study.index') }}" class="nav-link {{ Route::is('student.study*') ? 'nav-link-active' : '' }}"><x-ui.icon name="sparkles"/>Study</a>
                <a href="{{ route('student.topics.index') }}" class="nav-link {{ Route::is('student.topics*') ? 'nav-link-active' : '' }}"><x-ui.icon name="bulb"/>Topics</a>
            @endif
        </nav>

        <div class="px-3 py-3" style="border-top:1px solid var(--line)">
            <a href="{{ route('profile.edit') }}" class="nav-link">
                <x-ui.avatar :name="$authUser?->name ?? ''" class="!w-7 !h-7 !text-[.7rem]"/>
                <span class="flex flex-col min-w-0">
                    <span class="text-[.82rem] font-medium truncate" style="color:var(--ink)">{{ $authUser?->name }}</span>
                    <span class="text-[.7rem] faint">{{ $authUser?->roleLabel() }}</span>
                </span>
            </a>
        </div>
    </aside>

    {{-- ── Content ── --}}
    <div class="app-content">
        <header class="app-top">
            <div class="flex items-center gap-3 min-w-0 flex-1">
                <button class="app-hamburger lg:hidden" @click="sidebarOpen = true" aria-label="Open menu">
                    <x-ui.icon name="menu" class="w-5 h-5"/>
                </button>
                <h1 class="app-title">{{ $title ?? 'Dashboard' }}</h1>
                @isset($subtitle)
                    <span class="faint text-sm hidden sm:inline truncate">{{ $subtitle }}</span>
                @endisset
            </div>
            <div class="flex items-center gap-1.5 sm:gap-2 flex-none">
                @isset($actions)<div class="flex items-center gap-2">{{ $actions }}</div>@endisset
                <x-ui.theme-toggle />

                {{-- User dropdown --}}
                <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
                    <button type="button" class="user-menu-btn" @click="open = !open" aria-haspopup="menu" :aria-expanded="open">
                        <x-ui.avatar :name="$authUser?->name ?? ''"/>
                        <span class="hidden sm:flex flex-col items-start text-left">
                            <span class="user-menu-name">{{ $authUser?->name }}</span>
                            <span class="user-menu-role">{{ $authUser?->roleLabel() }}</span>
                        </span>
                        <x-ui.icon name="chevron-down" class="w-4 h-4 hidden sm:block faint"/>
                    </button>

                    <div class="menu absolute right-0 mt-2 z-50" x-show="open" x-cloak
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0" role="menu">
                        <div class="px-2.5 py-2">
                            <div class="text-sm font-medium truncate" style="color:var(--ink)">{{ $authUser?->name }}</div>
                            <div class="text-xs faint truncate">{{ $authUser?->email }}</div>
                            <div class="mt-1.5"><span class="badge badge-accent">{{ $authUser?->roleLabel() }}</span></div>
                        </div>
                        <div class="menu-sep"></div>
                        <a href="{{ route('profile.edit') }}" class="menu-item" role="menuitem">
                            <x-ui.icon name="user" class="w-4 h-4"/>Profile &amp; security
                        </a>
                        @if($authUser?->isAdmin() && $currentSchool)
                            <a href="{{ route('admin.settings') }}" class="menu-item" role="menuitem">
                                <x-ui.icon name="settings" class="w-4 h-4"/>School settings
                            </a>
                        @endif
                        @if($authUser?->hasTwoFactorEnabled())
                            <div class="menu-label flex items-center gap-1.5"><x-ui.icon name="shield" class="w-3.5 h-3.5"/>2FA active</div>
                        @endif
                        <div class="menu-sep"></div>
                        <form method="POST" action="{{ route('logout') }}">@csrf
                            <button type="submit" class="menu-item" role="menuitem">
                                <x-ui.icon name="logout" class="w-4 h-4"/>Log out
                            </button>
                        </form>
                    </div>
                </div>
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
