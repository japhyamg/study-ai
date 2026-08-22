<x-layouts.studyai title="Profile &amp; security" subtitle="{{ auth()->user()->email }}">
@php($user = auth()->user())

<div class="max-w-3xl space-y-6">

    {{-- ── Profile header ── --}}
    <div class="surface p-5 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
            <x-ui.avatar :name="$user->name" class="!w-14 !h-14 !text-lg"/>
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-lg font-semibold text-ink">{{ $user->name }}</h2>
                    <span class="badge badge-accent">{{ $user->roleLabel() }}</span>
                    @if($user->hasTwoFactorEnabled())<span class="badge badge-ok">2FA on</span>@endif
                </div>
                <p class="text-sm muted truncate">{{ $user->email }}</p>
                @if($user->currentSchool())
                    <p class="text-xs faint mt-0.5">{{ $user->currentSchool()->name }}@if($user->currentSchool()->slug) · {{ $user->currentSchool()->slug }}@endif</p>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Profile information ── --}}
    <section class="surface p-5 sm:p-6" id="profile">
        <h3 class="section-title text-ink mb-1">Profile information</h3>
        <p class="text-sm muted mb-4">Update your name and email address.</p>

        <form method="POST" action="{{ route('profile.update') }}" class="max-w-xl space-y-4">
            @csrf
            @method('PATCH')

            <div>
                <x-input-label for="name" :value="__('Name')" />
                <x-text-input id="name" name="name" type="text" :value="old('name', $user->name)" required autofocus autocomplete="name" class="block mt-1 w-full" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="email" :value="__('Email address')" />
                <x-text-input id="email" name="email" type="email" :value="old('email', $user->email)" required autocomplete="username" class="block mt-1 w-full" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="btn btn-primary">Save changes</button>
                @if($user->email_verified_at === null)
                    <p class="text-sm warn">
                        <a href="{{ route('verification.send') }}" class="underline">Your email is unverified — resend link</a>
                    </p>
                @endif
            </div>
        </form>
    </section>

    {{-- ── Password ── --}}
    <section class="surface p-5 sm:p-6" id="password">
        <h3 class="section-title text-ink mb-1">Password</h3>
        <p class="text-sm muted mb-4">Ensure your account uses a long, random password to stay secure.</p>

        <form method="POST" action="{{ route('password.update') }}" class="max-w-xl space-y-4">
            @csrf
            @method('PUT')

            <div>
                <x-input-label for="update_current_password" :value="__('Current password')" />
                <x-text-input id="update_current_password" name="current_password" type="password" required autocomplete="current-password" class="block mt-1 w-full" />
                <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="update_password" :value="__('New password')" />
                <x-text-input id="update_password" name="password" type="password" required autocomplete="new-password" class="block mt-1 w-full" />
                <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="update_password_confirmation" :value="__('Confirm new password')" />
                <x-text-input id="update_password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="block mt-1 w-full" />
                <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
            </div>

            <button type="submit" class="btn btn-primary">Update password</button>
        </form>
    </section>

    {{-- ── Two-factor authentication ── --}}
    <section class="surface p-5 sm:p-6" id="two-factor">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <div class="flex items-center gap-2">
                    <x-ui.icon name="shield" class="w-4 h-4" style="color:var(--accent)"/>
                    <h3 class="section-title text-ink">Two-factor authentication</h3>
                </div>
                <p class="text-sm muted mt-1 max-w-md">
                    Add an extra layer of security to your account. After enabling it you'll sign in
                    with your password plus a code from an authenticator app (Google Authenticator,
                    Authy, 1Password, …).
                </p>
            </div>
            @if($user->hasTwoFactorEnabled())
                <span class="badge badge-ok">Enabled</span>
            @elseif($setupSecret)
                <span class="badge badge-warn">Setup in progress</span>
            @else
                <span class="badge">Disabled</span>
            @endif
        </div>

        {{-- State 1: off → start --}}
        @if(! $setupSecret && ! $user->hasTwoFactorEnabled())
            <form method="POST" action="{{ route('two-factor.enable') }}" class="mt-4 max-w-xl space-y-3">
                @csrf
                <div class="max-w-xs">
                    <x-input-label for="enable_current_password" :value="__('Confirm your current password to continue')" />
                    <x-text-input id="enable_current_password" name="current_password" type="password" required autocomplete="current-password" class="block mt-1 w-full" />
                    <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
                </div>
                <button type="submit" class="btn btn-primary">Enable two-factor authentication</button>
            </form>
        @endif

        {{-- State 2: scan + confirm --}}
        @if($setupSecret)
            <div class="mt-5 grid gap-6 md:grid-cols-[auto_1fr] items-start">
                <div class="mx-auto">
                    <div id="qr" class="p-3 bg-white border rounded-lg inline-block" style="border-color:var(--line-strong)"></div>
                </div>
                <div>
                    <ol class="text-sm muted space-y-1.5 list-decimal pl-5">
                        <li>Open your authenticator app and choose “Add account”.</li>
                        <li>Scan the QR code, or enter the setup key manually.</li>
                        <li>Enter the 6-digit code below to confirm.</li>
                    </ol>
                    <div class="mt-3">
                        <span class="field-label">Setup key (manual entry)</span>
                        <code class="block text-xs px-3 py-2 rounded-md select-all" style="background:var(--paper-sunk);color:var(--ink)">{{ $setupSecret }}</code>
                    </div>
                    <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-4 flex flex-wrap items-start gap-3">
                        @csrf
                        <div class="w-40">
                            <x-input-label for="confirm_code" :value="__('Code')" />
                            <x-text-input id="confirm_code" name="code" type="text" inputmode="numeric" required autofocus
                                          class="block mt-1 w-full text-center tracking-[.3em] font-medium" placeholder="000000" />
                            <x-input-error :messages="$errors->get('code')" class="mt-2" />
                        </div>
                        <div class="flex gap-2 mt-6">
                            <button type="submit" class="btn btn-primary">Confirm &amp; enable</button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('two-factor.disable') }}" class="mt-3">
                        @csrf
                        <input type="hidden" name="current_password" value="">
                        <button type="submit" class="text-sm text-muted hover:text-danger">Cancel setup</button>
                    </form>
                </div>
            </div>
            @push('scripts')
                <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        if (window.QRCode) {
                            new QRCode(document.getElementById('qr'), {
                                text: @json($setupUri),
                                width: 168,
                                height: 168,
                                correctLevel: QRCode.CorrectLevel.M
                            });
                        }
                    });
                </script>
            @endpush
        @endif

        {{-- State 3: enabled --}}
        @if($user->hasTwoFactorEnabled())
            <div class="mt-4 pt-4 max-w-xl space-y-5" style="border-top:1px solid var(--line)">
                @if($recoveryCodes)
                    <div>
                        <span class="field-label">Recovery codes — store them somewhere safe (shown once)</span>
                        <div class="grid grid-cols-2 gap-1.5">
                            @foreach($recoveryCodes as $code)
                                <code class="text-xs px-3 py-1.5 rounded-md text-center select-all" style="background:var(--paper-sunk);color:var(--ink)">{{ $code }}</code>
                            @endforeach
                        </div>
                        <p class="text-xs faint mt-2">Each code can be used once to sign in if you lose access to your authenticator app.</p>
                    </div>
                @endif

                <form method="POST" action="{{ route('two-factor.regenerate') }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div class="flex-1 min-w-[12rem] max-w-xs">
                        <x-input-label for="regen_current_password" :value="__('Current password')" />
                        <x-text-input id="regen_current_password" name="current_password" type="password" required autocomplete="current-password" class="block mt-1 w-full" />
                        <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
                    </div>
                    <button type="submit" class="btn btn-outline">Generate new recovery codes</button>
                </form>

                <form method="POST" action="{{ route('two-factor.disable') }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div class="flex-1 min-w-[12rem] max-w-xs">
                        <x-input-label for="disable_current_password" :value="__('Current password')" />
                        <x-text-input id="disable_current_password" name="current_password" type="password" required autocomplete="current-password" class="block mt-1 w-full" />
                        <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
                    </div>
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Disable two-factor authentication?')">Disable 2FA</button>
                </form>
            </div>
        @endif
    </section>

    {{-- ── Danger zone ── --}}
    <section class="surface p-5 sm:p-6" id="danger">
        <h3 class="section-title text-ink mb-1">Delete account</h3>
        <p class="text-sm muted mb-4">Permanently delete your account and all of your data. This cannot be undone.</p>

        <form method="POST" action="{{ route('profile.destroy') }}"
              onsubmit="return confirm('Really delete your account? This is permanent.')">
            @csrf
            @method('DELETE')
            <div class="max-w-xs mb-3">
                <x-input-label for="delete_password" :value="__('Confirm your password to delete')" />
                <x-text-input id="delete_password" name="password" type="password" required autocomplete="current-password" class="block mt-1 w-full" />
                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>
            <button type="submit" class="btn btn-danger">Delete my account</button>
        </form>
    </section>
</div>
</x-layouts.studyai>
