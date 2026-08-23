<x-guest-layout title="Two-factor authentication"
                description="Enter the 6-digit code from your authenticator app.">

    <div x-data="{ recovery: false }" class="space-y-4">

        {{-- Authenticator code --}}
        <form x-show="!recovery" method="POST" action="{{ route('two-factor.verify') }}" class="space-y-4">
            @csrf

            <x-ui.field label="Authentication code" name="code">
                <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code"
                       pattern="[0-9]{6}" maxlength="6" required autofocus
                       class="input text-center font-mono text-lg tracking-[0.5em]"
                       placeholder="000000">
            </x-ui.field>

            <button type="submit" class="btn btn-primary btn-block btn-lg">Verify</button>

            <button type="button" class="btn btn-ghost btn-block btn-sm" @click="recovery = true">
                Use a recovery code instead
            </button>
        </form>

        {{-- Recovery code --}}
        <form x-show="recovery" x-cloak method="POST" action="{{ route('two-factor.verify') }}" class="space-y-4">
            @csrf

            <x-ui.field label="Recovery code" name="recovery_code"
                        hint="One of the one-time codes you saved when enabling two-factor.">
                <input id="recovery_code" name="recovery_code" type="text" required
                       class="input text-center font-mono tracking-wider" placeholder="XXXXX-XXXXX">
            </x-ui.field>

            <button type="submit" class="btn btn-primary btn-block btn-lg">Verify</button>

            <button type="button" class="btn btn-ghost btn-block btn-sm" @click="recovery = false">
                Use an authenticator code instead
            </button>
        </form>
    </div>

    <x-slot:footer>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="text-sm text-muted hover:text-ink hover:underline">Back to sign in</button>
        </form>
    </x-slot:footer>
</x-guest-layout>
