<x-guest-layout :title="isset($title) ? $title : 'Sign in'">
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        {{-- Email --}}
        <div>
            <x-input-label for="email" :value="__('Email address')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="you@school.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        {{-- Password --}}
        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" placeholder="••••••••" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        {{-- Remember --}}
        <div class="flex items-center justify-between">
            <label for="remember_me" class="inline-flex items-center cursor-pointer">
                <input id="remember_me" type="checkbox" class="rounded border-line-strong text-accent focus:ring-accent" name="remember">
                <span class="ms-2 text-sm text-muted">{{ __('Remember me') }}</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-sm text-muted hover:text-accent" href="{{ route('password.request') }}">
                    {{ __('Forgot password?') }}
                </a>
            @endif
        </div>

        <x-primary-button class="btn-block !mt-6">
            {{ __('Sign in') }}
        </x-primary-button>
    </form>

    @if (Route::has('register'))
        <p class="text-sm text-muted text-center mt-5">
            {{ __('New here?') }}
            <a href="{{ route('register') }}" class="text-accent font-medium">{{ __('Create an account') }}</a>
        </p>
    @endif
</x-guest-layout>
