@php
    use App\Support\TwoFactor\QrCode;
    use App\Support\TwoFactor\TotpAuthenticator;

    $tabs = ['profile' => ['Profile', 'user'], 'security' => ['Security', 'shield']];
    $tab = in_array($tab ?? 'profile', array_keys($tabs), true) ? $tab : 'profile';
    $enabled = $admin->hasTwoFactorEnabled();
    $pending = $admin->hasTwoFactorPending();
    $secret  = session('2fa:setup:secret');
@endphp

<x-layouts.studyai title="Your profile" subtitle="Platform staff account">

    <div class="surface mb-5 flex flex-wrap items-center gap-4 p-4 sm:p-5">
        <span class="avatar avatar-xl">{{ $admin->initials() }}</span>
        <div class="min-w-0 flex-1">
            <h2 class="truncate text-base font-semibold text-ink">{{ $admin->name }}</h2>
            <p class="truncate text-sm text-muted">{{ $admin->email }}</p>
            <div class="mt-1.5 flex flex-wrap gap-1.5">
                <span class="badge badge-accent">Super Admin</span>
                @if ($enabled)
                    <span class="badge badge-ok"><span class="dot"></span> 2FA on</span>
                @else
                    <span class="badge badge-warn"><span class="dot"></span> 2FA off</span>
                @endif
            </div>
        </div>
    </div>

    <nav class="tabs mb-5">
        @foreach ($tabs as $key => [$label, $icon])
            <a href="{{ route('super-admin.profile.edit', ['tab' => $key]) }}"
               @class(['tab-link inline-flex items-center gap-1.5', 'active' => $tab === $key])>
                <x-icon :name="$icon" class="h-4 w-4" /> {{ $label }}
            </a>
        @endforeach
    </nav>

    @if ($tab === 'profile')
        <div class="max-w-2xl">
            <x-ui.card title="Personal information">
                <form method="POST" action="{{ route('super-admin.profile.update') }}" class="space-y-4">
                    @csrf
                    @method('put')
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.field label="Full name" name="name" required>
                            <input name="name" class="input" value="{{ old('name', $admin->name) }}" required>
                        </x-ui.field>
                        <x-ui.field label="Email address" name="email" required>
                            <input name="email" type="email" class="input" value="{{ old('email', $admin->email) }}" required>
                        </x-ui.field>
                        <x-ui.field label="Phone" name="phone">
                            <input name="phone" type="tel" class="input" value="{{ old('phone', $admin->phone) }}">
                        </x-ui.field>
                    </div>
                    <div class="flex justify-end"><button class="btn btn-primary">Save changes</button></div>
                </form>
            </x-ui.card>
        </div>
    @endif

    @if ($tab === 'security')
        <div class="grid max-w-5xl gap-5 lg:grid-cols-2">
            <x-ui.card title="Password">
                <form method="POST" action="{{ route('super-admin.profile.password') }}" class="space-y-4">
                    @csrf
                    @method('put')
                    <x-ui.field label="Current password" name="current_password"
                                :error="$errors->updatePassword->first('current_password')">
                        <input name="current_password" type="password" class="input" required autocomplete="current-password">
                    </x-ui.field>
                    <x-ui.field label="New password" name="password"
                                :error="$errors->updatePassword->first('password')">
                        <input name="password" type="password" class="input" required autocomplete="new-password">
                    </x-ui.field>
                    <x-ui.field label="Confirm new password" name="password_confirmation">
                        <input name="password_confirmation" type="password" class="input" required autocomplete="new-password">
                    </x-ui.field>
                    <div class="flex justify-end"><button class="btn btn-primary">Update password</button></div>
                </form>
            </x-ui.card>

            <x-ui.card title="Two-factor authentication">
                <x-slot:actions>
                    <span class="badge {{ $enabled ? 'badge-ok' : ($pending ? 'badge-warn' : '') }}">
                        {{ $enabled ? 'Enabled' : ($pending ? 'Setup incomplete' : 'Disabled') }}
                    </span>
                </x-slot:actions>

                <div class="space-y-4">
                    <p class="text-sm text-muted">Platform accounts should always use two-factor authentication.</p>

                    @if ($pending && $secret)
                        <div class="surface-sunk space-y-4 p-4">
                            <div class="mx-auto w-fit rounded-lg bg-white p-2">
                                {!! QrCode::svg($admin->twoFactorQrUri($secret, config('app.name').' Platform'), 168) !!}
                            </div>
                            <code class="block break-all rounded bg-surface px-2 py-1.5 text-center font-mono text-xs tracking-wider text-ink">
                                {{ TotpAuthenticator::formatForDisplay($secret) }}
                            </code>
                            <form method="POST" action="{{ route('super-admin.two-factor.confirm') }}" class="space-y-3">
                                @csrf
                                <x-ui.field label="Enter the 6-digit code" name="code">
                                    <input name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                                           required autofocus class="input text-center font-mono text-base tracking-[0.4em]"
                                           placeholder="000000">
                                </x-ui.field>
                                <button class="btn btn-primary">Confirm and enable</button>
                            </form>
                        </div>
                    @endif

                    @if (session('2fa:show-recovery') && $admin->recoveryCodes()->isNotEmpty())
                        <div class="alert alert-warn flex-col items-start gap-2">
                            <p class="font-medium">Save your recovery codes</p>
                            <div class="grid w-full grid-cols-2 gap-1.5 font-mono text-xs">
                                @foreach ($admin->recoveryCodes() as $code)
                                    <span class="rounded bg-surface px-2 py-1 text-ink">{{ $code }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if (! $enabled && ! $pending)
                        <form method="POST" action="{{ route('super-admin.two-factor.enable') }}">
                            @csrf
                            <button class="btn btn-primary"><x-icon name="shield" /> Enable two-factor</button>
                        </form>
                    @endif

                    @if ($enabled)
                        <div class="space-y-3 border-t pt-4" x-data="{ mode: null }">
                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="btn btn-outline btn-sm" @click="mode = mode === 'regen' ? null : 'regen'">
                                    <x-icon name="key" /> New recovery codes
                                </button>
                                <button type="button" class="btn btn-danger-quiet btn-sm" @click="mode = mode === 'disable' ? null : 'disable'">
                                    <x-icon name="trash" /> Disable
                                </button>
                            </div>
                            <form x-show="mode === 'regen'" x-cloak method="POST" action="{{ route('super-admin.two-factor.recovery-codes') }}" class="space-y-2">
                                @csrf
                                <x-ui.field label="Confirm your password" name="password">
                                    <input name="password" type="password" class="input max-w-xs" required>
                                </x-ui.field>
                                <button class="btn btn-primary btn-sm">Generate new codes</button>
                            </form>
                            <form x-show="mode === 'disable'" x-cloak method="POST" action="{{ route('super-admin.two-factor.disable') }}" class="space-y-2">
                                @csrf
                                @method('delete')
                                <x-ui.field label="Confirm your password" name="password">
                                    <input name="password" type="password" class="input max-w-xs" required>
                                </x-ui.field>
                                <button class="btn btn-danger btn-sm">Disable two-factor</button>
                            </form>
                        </div>
                    @endif
                </div>
            </x-ui.card>
        </div>
    @endif
</x-layouts.studyai>
