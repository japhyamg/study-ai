@php
    use App\Support\TwoFactor\QrCode;
    use App\Support\TwoFactor\TotpAuthenticator;

    $enabled = $user->hasTwoFactorEnabled();
    $pending = $user->hasTwoFactorPending();
    $secret  = session('2fa:setup:secret');
    $showRecovery = session('2fa:show-recovery');
@endphp

<x-ui.card>
    <x-slot:actions>
        @if ($enabled)
            <span class="badge badge-ok"><span class="dot"></span> Enabled</span>
        @elseif ($pending)
            <span class="badge badge-warn"><span class="dot"></span> Setup incomplete</span>
        @else
            <span class="badge">Disabled</span>
        @endif
    </x-slot:actions>

    <x-slot:title>Two-factor authentication</x-slot:title>

    <div class="space-y-4">
        <p class="text-sm text-muted">
            Add a second step to sign-in using an authenticator app such as Google
            Authenticator, 1Password or Authy.
        </p>

        {{-- ── Enrolment: QR + confirm ── --}}
        @if ($pending && $secret)
            <div class="surface-sunk space-y-4 p-4">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                    <div class="mx-auto flex-none rounded-lg bg-white p-2 sm:mx-0">
                        {!! QrCode::svg($user->twoFactorQrUri($secret, $user->school?->name), 168) !!}
                    </div>

                    <div class="min-w-0 flex-1 space-y-2">
                        <p class="text-sm font-medium text-ink">1. Scan this code</p>
                        <p class="text-xs text-muted">
                            Can't scan? Enter this key manually:
                        </p>
                        <code class="block break-all rounded bg-surface px-2 py-1.5 font-mono text-xs tracking-wider text-ink">
                            {{ TotpAuthenticator::formatForDisplay($secret) }}
                        </code>
                    </div>
                </div>

                <form method="POST" action="{{ route('two-factor.confirm') }}" class="space-y-3">
                    @csrf
                    <x-ui.field label="2. Enter the 6-digit code" name="code">
                        <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code"
                               pattern="[0-9]{6}" maxlength="6" required autofocus
                               class="input max-w-[10rem] text-center font-mono text-base tracking-[0.4em]"
                               placeholder="000000">
                    </x-ui.field>

                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">Confirm and enable</button>
                        <button type="submit" form="disable-2fa-cancel" class="btn btn-ghost">Cancel</button>
                    </div>
                </form>
            </div>

            {{-- Cancelling an incomplete setup needs no password. --}}
            <form id="disable-2fa-cancel" method="POST" action="{{ route('two-factor.disable') }}" class="hidden">
                @csrf
                @method('delete')
                <input type="hidden" name="password" value="">
            </form>
        @endif

        {{-- ── Recovery codes ── --}}
        @if ($showRecovery && $user->recoveryCodes()->isNotEmpty())
            <div class="alert alert-warn flex-col items-start gap-2">
                <p class="font-medium">Save your recovery codes</p>
                <p class="text-xs">
                    Each code works once if you lose access to your device. Store them somewhere safe —
                    they will not be shown again.
                </p>
                <div class="grid w-full grid-cols-2 gap-1.5 font-mono text-xs">
                    @foreach ($user->recoveryCodes() as $code)
                        <span class="rounded bg-surface px-2 py-1 text-ink">{{ $code }}</span>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- ── Actions ── --}}
        @if (! $enabled && ! $pending)
            <form method="POST" action="{{ route('two-factor.enable') }}">
                @csrf
                <button type="submit" class="btn btn-primary">
                    <x-icon name="shield" /> Enable two-factor
                </button>
            </form>
        @endif

        @if ($enabled)
            <div class="space-y-3 border-t pt-4" x-data="{ mode: null }">
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline btn-sm"
                            @click="mode = mode === 'regen' ? null : 'regen'">
                        <x-icon name="key" /> New recovery codes
                    </button>
                    <button type="button" class="btn btn-danger-quiet btn-sm"
                            @click="mode = mode === 'disable' ? null : 'disable'">
                        <x-icon name="trash" /> Disable
                    </button>
                </div>

                <form x-show="mode === 'regen'" x-cloak method="POST"
                      action="{{ route('two-factor.recovery-codes') }}" class="space-y-2">
                    @csrf
                    <x-ui.field label="Confirm your password" name="password">
                        <input name="password" type="password" class="input max-w-xs" required autocomplete="current-password">
                    </x-ui.field>
                    <button type="submit" class="btn btn-primary btn-sm">Generate new codes</button>
                </form>

                <form x-show="mode === 'disable'" x-cloak method="POST"
                      action="{{ route('two-factor.disable') }}" class="space-y-2">
                    @csrf
                    @method('delete')
                    <p class="text-xs text-muted">Your account will be protected by password only.</p>
                    <x-ui.field label="Confirm your password" name="password">
                        <input name="password" type="password" class="input max-w-xs" required autocomplete="current-password">
                    </x-ui.field>
                    <button type="submit" class="btn btn-danger btn-sm">Disable two-factor</button>
                </form>
            </div>
        @endif
    </div>
</x-ui.card>
