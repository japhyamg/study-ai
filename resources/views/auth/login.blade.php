<x-guest-layout title="Sign in" :description="($tenant?->name ?? 'Your school').' — administrators, teachers and students'">

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        {{-- One field for both: staff have an email address, students are given
             an admission number and usually have no school email at all. --}}
        <x-ui.field label="Email or admission number" name="login" required
                    hint="Students: use the admission number your school gave you.">
            <input id="login" name="login" type="text" class="input"
                   value="{{ old('login') }}" required autofocus autocomplete="username"
                   placeholder="you@school.edu">
        </x-ui.field>

        <x-ui.field label="Password" name="password" required>
            <input id="password" name="password" type="password" class="input"
                   required autocomplete="current-password" placeholder="••••••••">
        </x-ui.field>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <label for="remember_me" class="inline-flex cursor-pointer items-center gap-2 text-sm text-muted">
                <input id="remember_me" name="remember" type="checkbox" class="checkbox">
                Keep me signed in
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-sm font-medium text-accent hover:underline">
                    Forgot password?
                </a>
            @endif
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg">Sign in</button>
    </form>

    <x-slot:footer>
        @if (Route::has('register'))
            New here? <a href="{{ route('register') }}" class="font-medium text-accent hover:underline">Create an account</a>
        @endif
    </x-slot:footer>
</x-guest-layout>
