@php
    use App\Models\SchoolMember;

    // Resolve each principal from its own guard explicitly.
    //
    // `auth()->user()` cannot be trusted here: the auth middleware calls
    // shouldUse() on whichever guard authenticated the request, so on
    // super-admin routes the *default* guard becomes `superadmin` and
    // auth()->user() returns a SuperAdmin. Calling a school-user method such
    // as roleInSchool() on it then throws BadMethodCallException, and `?->`
    // does not help — it guards against null, not against the wrong type.
    $user       = auth('web')->user();
    $superAdmin = auth('superadmin')->user();
    $principal  = $superAdmin ?? $user;
    $isPlatform = (bool) $superAdmin;
    $role       = $user?->roleInSchool();
    $school     = $tenant ?? $user?->currentSchool();
    $nav        = $navigation ?? \App\Support\Navigation::for($principal, $isPlatform);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#13171f" media="(prefers-color-scheme: dark)">

    <title>{{ $title ?? 'Dashboard' }} · {{ $school?->name ?? config('app.name', 'StudyAI') }}</title>

    {{-- Flash of wrong theme is prevented by setting the class before paint. --}}
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

    {{-- KaTeX — only pulled in where study content needs it --}}
    @stack('head')
    @stack('styles')
</head>
<body class="h-full">

<a href="#main" class="sr-only-focusable absolute left-4 top-4 z-[100] btn btn-primary btn-sm">Skip to content</a>

<div class="app-shell" x-data="appShell()" @keydown.escape.window="sidebarOpen = false">

    {{-- ───────────────── Mobile scrim ───────────────── --}}
    <div
        x-show="sidebarOpen"
        x-transition.opacity
        @click="sidebarOpen = false"
        class="fixed inset-0 z-40 bg-ink/40 backdrop-blur-[1px] lg:hidden"
        style="display:none"
        aria-hidden="true"
    ></div>

    {{-- ───────────────── Sidebar ───────────────── --}}
    <aside
        class="app-side"
        :data-open="sidebarOpen"
        x-ref="sidebar"
        aria-label="Main navigation"
    >
        {{-- Brand / tenant --}}
        <div class="flex h-topbar flex-none items-center gap-2.5 border-b px-4">
            <a href="{{ $isPlatform ? route('super-admin.dashboard') : route('dashboard') }}"
               class="flex min-w-0 items-center gap-2.5">
                <span class="grid h-8 w-8 flex-none place-items-center rounded-lg bg-brand-600 text-sm font-bold text-white">
                    {{ $isPlatform ? 'S' : ($school?->initials() ?? 'S') }}
                </span>
                <span class="min-w-0">
                    <span class="block truncate text-sm font-semibold text-ink">
                        {{ $isPlatform ? config('app.name', 'StudyAI') : ($school?->name ?? 'StudyAI') }}
                    </span>
                    <span class="block truncate text-2xs text-faint">
                        {{ $isPlatform ? 'Platform console' : ($school?->subdomain ? $school->subdomain.'.'.(config('tenancy.domain') ?? 'studyai') : 'School workspace') }}
                    </span>
                </span>
            </a>

            <button type="button"
                    class="btn btn-ghost btn-icon ms-auto lg:hidden"
                    @click="sidebarOpen = false"
                    aria-label="Close navigation">
                <x-icon name="x" />
            </button>
        </div>

        {{-- Links --}}
        <nav class="flex-1 space-y-0.5 overflow-y-auto px-3 py-3">
            @foreach ($nav as $section => $links)
                @if (! empty($links))
                    <p class="nav-section">{{ $section }}</p>
                    @foreach ($links as $link)
                        <a href="{{ $link['url'] }}"
                           @class(['nav-link', 'nav-link-active' => $link['active']])
                           @if($link['active']) aria-current="page" @endif>
                            <x-icon :name="$link['icon']" />
                            <span class="truncate">{{ $link['label'] }}</span>
                            @isset($link['badge'])
                                <span class="badge badge-accent ms-auto">{{ $link['badge'] }}</span>
                            @endisset
                        </a>
                    @endforeach
                @endif
            @endforeach
        </nav>

        {{-- Sidebar footer: compact identity, full menu lives in the topbar --}}
        <div class="flex-none border-t p-3">
            @if ($isPlatform)
                <a href="{{ route('super-admin.profile.edit') }}" class="nav-link">
                    <x-icon name="cog" />
                    <span>Account settings</span>
                </a>
            @else
                <a href="{{ route('profile.edit') }}" class="nav-link">
                    <x-icon name="cog" />
                    <span>Account settings</span>
                </a>
            @endif
        </div>
    </aside>

    {{-- ───────────────── Content column ───────────────── --}}
    <div class="app-content">

        {{-- Topbar --}}
        <header class="app-top">
            <button type="button"
                    class="btn btn-ghost btn-icon lg:hidden"
                    @click="sidebarOpen = true"
                    aria-label="Open navigation">
                <x-icon name="menu" />
            </button>

            {{-- Page title: hidden on mobile where space is tight --}}
            <div class="min-w-0 flex-1">
                <h1 class="truncate text-sm font-semibold text-ink sm:text-[0.9375rem]">
                    {{ $title ?? 'Dashboard' }}
                </h1>
                @isset($subtitle)
                    <p class="hidden truncate text-xs text-faint sm:block">{{ $subtitle }}</p>
                @endisset
            </div>

            {{-- Contextual actions --}}
            @isset($actions)
                <div class="flex items-center gap-2">{{ $actions }}</div>
            @endisset

            <div class="flex items-center gap-1">
                {{-- Theme --}}
                <button type="button"
                        class="btn btn-ghost btn-icon"
                        @click="toggleTheme()"
                        :aria-label="dark ? 'Switch to light theme' : 'Switch to dark theme'">
                    <x-icon name="sun" class="hidden dark:block" />
                    <x-icon name="moon" class="dark:hidden" />
                </button>

                {{-- ───── Logged-in user menu ───── --}}
                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                    <button type="button"
                            @click="open = !open"
                            class="flex items-center gap-2 rounded-lg py-1 pe-1 ps-1 transition-colors hover:bg-surface-sunk sm:pe-2"
                            :aria-expanded="open.toString()"
                            aria-haspopup="menu">
                        <span class="avatar">
                            @if (! $isPlatform && $user?->avatarUrl())
                                <img src="{{ $user->avatarUrl() }}" alt="">
                            @else
                                {{ $principal?->initials() }}
                            @endif
                        </span>
                        <span class="hidden min-w-0 text-start sm:block">
                            <span class="block max-w-[10rem] truncate text-xs font-medium leading-tight text-ink">
                                {{ $principal?->name }}
                            </span>
                            <span class="block max-w-[10rem] truncate text-2xs leading-tight text-faint">
                                {{ $isPlatform ? 'Super Admin' : $user?->roleLabel() }}
                            </span>
                        </span>
                        <x-icon name="chevron-down" class="hidden text-faint sm:block" />
                    </button>

                    <div x-show="open"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-cloak
                         class="popover absolute end-0 z-50 mt-2 w-64"
                         role="menu">

                        {{-- Identity block --}}
                        <div class="flex items-center gap-2.5 px-2 py-2">
                            <span class="avatar avatar-lg">
                                @if (! $isPlatform && $user?->avatarUrl())
                                    <img src="{{ $user->avatarUrl() }}" alt="">
                                @else
                                    {{ $principal?->initials() }}
                                @endif
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-ink">{{ $principal?->name }}</p>
                                <p class="truncate text-xs text-faint">{{ $principal?->email }}</p>
                                <p class="mt-1">
                                    <span class="badge {{ $isPlatform ? 'badge-accent' : '' }}">
                                        {{ $isPlatform ? 'Super Admin' : $user?->roleLabel() }}
                                    </span>
                                </p>
                            </div>
                        </div>

                        <div class="menu-sep"></div>

                        @if ($isPlatform)
                            <a href="{{ route('super-admin.profile.edit') }}" class="menu-item" role="menuitem">
                                <x-icon name="user" /> Your profile
                            </a>
                            <a href="{{ route('super-admin.profile.edit', ['tab' => 'security']) }}" class="menu-item" role="menuitem">
                                <x-icon name="shield" /> Password &amp; 2FA
                            </a>
                        @else
                            <a href="{{ route('profile.edit') }}" class="menu-item" role="menuitem">
                                <x-icon name="user" /> Your profile
                            </a>
                            <a href="{{ route('profile.edit', ['tab' => 'security']) }}" class="menu-item" role="menuitem">
                                <x-icon name="shield" /> Password &amp; 2FA
                                @if ($user && ! $user->hasTwoFactorEnabled())
                                    <span class="badge badge-warn ms-auto">Off</span>
                                @endif
                            </a>
                            <a href="{{ route('profile.edit', ['tab' => 'preferences']) }}" class="menu-item" role="menuitem">
                                <x-icon name="sliders" /> Preferences
                            </a>
                        @endif

                        <div class="menu-sep"></div>

                        @if (session('impersonator_id'))
                            <form method="POST" action="{{ route('impersonate.stop') }}">
                                @csrf
                                <button type="submit" class="menu-item" role="menuitem">
                                    <x-icon name="logout" /> Stop impersonating
                                </button>
                            </form>
                        @else
                            <form method="POST" action="{{ $isPlatform ? route('super-admin.logout') : route('logout') }}">
                                @csrf
                                <button type="submit" class="menu-item menu-item-danger" role="menuitem">
                                    <x-icon name="logout" /> Sign out
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </header>

        {{-- Impersonation banner --}}
        @if (session('impersonator_id'))
            <div class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 border-b border-warning/25 bg-warning-soft px-4 py-2 text-xs text-warning">
                <span class="font-medium">
                    Support session — you are signed in as {{ $user?->name }}.
                </span>
                <form method="POST" action="{{ route('impersonate.stop') }}">
                    @csrf
                    <button class="font-semibold underline underline-offset-2">Exit</button>
                </form>
            </div>
        @endif

        {{-- Main --}}
        <main id="main" class="app-main">
            <div class="app-main-inner">

                @if (isset($header))
                    <div class="page-head">{{ $header }}</div>
                @endif

                {{-- Flash messages.

                     These are transient confirmations, so they dismiss
                     themselves. Validation errors below do NOT: the user has
                     to act on those, and a message that vanishes mid-read is
                     worse than no message. Dismissal is opacity + a delayed
                     collapse so the layout does not jump while it fades. --}}
                @if (session('status'))
                    <div class="alert alert-ok mb-4" role="status"
                         x-data="{ show: true }"
                         x-init="setTimeout(() => show = false, 6000)"
                         x-show="show"
                         x-transition.opacity.duration.400ms>
                        <x-icon name="check-circle" class="mt-px flex-none" />
                        <span>{{ session('status') }}</span>
                        <button type="button" class="ms-auto shrink-0 opacity-60 hover:opacity-100"
                                aria-label="Dismiss" @click="show = false">
                            <x-icon name="x" />
                        </button>
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger mb-4" role="alert"
                         x-data="{ show: true }"
                         x-init="setTimeout(() => show = false, 10000)"
                         x-show="show"
                         x-transition.opacity.duration.400ms>
                        <x-icon name="alert-circle" class="mt-px flex-none" />
                        <span>{{ session('error') }}</span>
                        <button type="button" class="ms-auto shrink-0 opacity-60 hover:opacity-100"
                                aria-label="Dismiss" @click="show = false">
                            <x-icon name="x" />
                        </button>
                    </div>
                @endif

                @if (isset($errors) && $errors->any() && ! $errors->hasBag('userDeletion'))
                    <div class="alert alert-danger mb-4" role="alert">
                        <x-icon name="alert-circle" class="mt-px flex-none" />
                        <div>
                            <p class="font-medium">Please check the following:</p>
                            <ul class="mt-1 list-inside list-disc space-y-0.5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                {{ $slot }}
            </div>
        </main>
    </div>
</div>

<script>
    function appShell() {
        return {
            sidebarOpen: false,
            dark: document.documentElement.classList.contains('dark'),
            toggleTheme() {
                this.dark = !this.dark;
                document.documentElement.classList.toggle('dark', this.dark);
                try { localStorage.setItem('theme', this.dark ? 'dark' : 'light'); } catch (e) {}
            },
        };
    }
</script>

@stack('scripts')
</body>
</html>
