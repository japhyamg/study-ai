@php
    $tabs = [
        'profile' => ['Profile', 'user'],
        'details' => [$user->roleLabel().' details', 'clipboard'],
        'security' => ['Security', 'shield'],
        'preferences' => ['Preferences', 'sliders'],
    ];
    $tab = in_array($tab ?? 'profile', array_keys($tabs), true) ? $tab : 'profile';
@endphp

<x-layouts.studyai title="Your profile" subtitle="Manage your account, security and preferences">

    {{-- Identity summary --}}
    <div class="surface mb-5 flex flex-wrap items-center gap-4 p-4 sm:p-5">
        <span class="avatar avatar-xl">
            @if ($user->avatarUrl())
                <img src="{{ $user->avatarUrl() }}" alt="">
            @else
                {{ $user->initials() }}
            @endif
        </span>

        <div class="min-w-0 flex-1">
            <h2 class="truncate text-base font-semibold text-ink">{{ $user->name }}</h2>
            <p class="truncate text-sm text-muted">{{ $user->email }}</p>
            <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                <span class="badge badge-accent">{{ $user->roleLabel() }}</span>
                <span class="badge">{{ $user->school?->name }}</span>
                @if ($user->hasTwoFactorEnabled())
                    <span class="badge badge-ok"><span class="dot"></span> 2FA on</span>
                @else
                    <span class="badge badge-warn"><span class="dot"></span> 2FA off</span>
                @endif
            </div>
        </div>

        @if ($user->last_login_at)
            <div class="text-xs text-faint">
                Last sign-in<br>
                <span class="text-muted">{{ $user->last_login_at->diffForHumans() }}</span>
            </div>
        @endif
    </div>

    {{-- Tabs --}}
    <nav class="tabs mb-5" aria-label="Profile sections">
        @foreach ($tabs as $key => [$label, $icon])
            <a href="{{ route('profile.edit', ['tab' => $key]) }}"
               @class(['tab-link inline-flex items-center gap-1.5', 'active' => $tab === $key])
               @if($tab === $key) aria-current="page" @endif>
                <x-icon :name="$icon" class="h-4 w-4" />
                <span>{{ $label }}</span>
            </a>
        @endforeach
    </nav>

    {{-- ─────────────── Profile ─────────────── --}}
    @if ($tab === 'profile')
        <div class="grid gap-5 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <x-ui.card title="Personal information" subtitle="How your name appears across the school.">
                    <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf
                        @method('patch')

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.field label="Full name" name="name" required>
                                <input id="name" name="name" type="text" class="input"
                                       value="{{ old('name', $user->name) }}" required autocomplete="name">
                            </x-ui.field>

                            <x-ui.field label="Email address" name="email" required>
                                <input id="email" name="email" type="email" class="input"
                                       value="{{ old('email', $user->email) }}" required autocomplete="email">
                            </x-ui.field>

                            <x-ui.field label="Phone" name="phone">
                                <input id="phone" name="phone" type="tel" class="input"
                                       value="{{ old('phone', $user->phone) }}" autocomplete="tel">
                            </x-ui.field>

                            <x-ui.field label="Profile photo" name="avatar" hint="JPG or PNG, up to 2 MB.">
                                <input id="avatar" name="avatar" type="file" accept="image/*"
                                       class="input file:me-3 file:rounded file:border-0 file:bg-surface-sunk file:px-2 file:py-1 file:text-xs file:font-medium file:text-ink">
                            </x-ui.field>
                        </div>

                        @if ($user->email_verified_at === null)
                            <div class="alert alert-warn">
                                <x-icon name="alert-circle" class="mt-px flex-none" />
                                <span>Your email address is not verified.</span>
                            </div>
                        @endif

                        <div class="flex justify-end">
                            <button type="submit" class="btn btn-primary">Save changes</button>
                        </div>
                    </form>
                </x-ui.card>
            </div>

            <div class="space-y-5">
                <x-ui.card title="Account">
                    <dl class="dl -my-2.5">
                        <div><dt>School</dt><dd>{{ $user->school?->name ?? '—' }}</dd></div>
                        <div><dt>Role</dt><dd>{{ $user->roleLabel() }}</dd></div>
                        <div><dt>Member since</dt><dd>{{ $user->created_at?->format('j M Y') }}</dd></div>
                        <div><dt>Status</dt><dd>
                            <span class="badge {{ $user->is_active ? 'badge-ok' : 'badge-danger' }}">
                                {{ $user->is_active ? 'Active' : 'Deactivated' }}
                            </span>
                        </dd></div>
                    </dl>
                </x-ui.card>

                @include('profile.partials.delete-user-form')
            </div>
        </div>
    @endif

    {{-- ─────────────── Role details ─────────────── --}}
    @if ($tab === 'details')
        <div class="max-w-3xl">
            @include('profile.partials.role-details-form')
        </div>
    @endif

    {{-- ─────────────── Security ─────────────── --}}
    @if ($tab === 'security')
        <div class="grid max-w-5xl gap-5 lg:grid-cols-2">
            @include('profile.partials.update-password-form')
            @include('profile.partials.two-factor-form')
        </div>
    @endif

    {{-- ─────────────── Preferences ─────────────── --}}
    @if ($tab === 'preferences')
        <div class="max-w-2xl">
            <x-ui.card title="Preferences" subtitle="Language and time settings for your account.">
                <form method="POST" action="{{ route('profile.preferences') }}" class="space-y-4">
                    @csrf
                    @method('put')

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.field label="Language" name="locale">
                            <select id="locale" name="locale" class="select">
                                @foreach (['en' => 'English', 'fr' => 'Français', 'es' => 'Español'] as $code => $label)
                                    <option value="{{ $code }}" @selected(old('locale', $user->locale) === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>

                        <x-ui.field label="Time zone" name="timezone">
                            <select id="timezone" name="timezone" class="select">
                                <option value="">Use school default</option>
                                @foreach (['Africa/Lagos', 'Africa/Accra', 'Africa/Nairobi', 'Europe/London', 'America/New_York', 'UTC'] as $tz)
                                    <option value="{{ $tz }}" @selected(old('timezone', $user->timezone) === $tz)>{{ $tz }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="btn btn-primary">Save preferences</button>
                    </div>
                </form>
            </x-ui.card>
        </div>
    @endif
</x-layouts.studyai>
