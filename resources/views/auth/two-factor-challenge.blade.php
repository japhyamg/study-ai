<x-guest-layout title="Two-factor authentication">
    <div class="mb-5">
        <div class="flex items-center gap-2.5 mb-1.5">
            <x-ui.icon name="shield" class="w-5 h-5" style="color:var(--accent)"/>
            <span class="font-semibold text-ink">Verification required</span>
        </div>
        <p class="text-sm muted">
            Enter the 6-digit code from your authenticator app, or one of your
            recovery codes, to finish signing in.
        </p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('two-factor.verify') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="code" :value="__('Authentication code')" />
            <x-text-input id="code" class="block mt-1 w-full text-center tracking-[.4em] font-medium"
                          type="text" inputmode="numeric" autocomplete="one-time-code"
                          name="code" placeholder="000000" required autofocus />
            <x-input-error :messages="$errors->get('code')" class="mt-2" />
        </div>

        <x-primary-button class="btn-block !mt-6">
            {{ __('Verify and sign in') }}
        </x-primary-button>
    </form>

    <div class="text-center mt-5">
        <form method="POST" action="{{ route('two-factor.cancel') }}">@csrf
            <button type="submit" class="text-sm text-muted hover:text-danger">
                {{ __('Use a different account') }}
            </button>
        </form>
    </div>
</x-guest-layout>
