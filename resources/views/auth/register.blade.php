<x-guest-layout title="Create your account"
                :description="($tenant?->name ?? 'Your school').' — join with an invite code'">

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <x-ui.field label="Full name" name="name" required>
            <input id="name" name="name" type="text" class="input"
                   value="{{ old('name') }}" required autofocus autocomplete="name">
        </x-ui.field>

        <x-ui.field label="Email address" name="email" required>
            <input id="email" name="email" type="email" class="input"
                   value="{{ old('email') }}" required autocomplete="username">
        </x-ui.field>

        <x-ui.field label="Password" name="password" required hint="At least 8 characters.">
            <input id="password" name="password" type="password" class="input"
                   required autocomplete="new-password">
        </x-ui.field>

        <x-ui.field label="Confirm password" name="password_confirmation" required>
            <input id="password_confirmation" name="password_confirmation" type="password"
                   class="input" required autocomplete="new-password">
        </x-ui.field>

        <button type="submit" class="btn btn-primary btn-block btn-lg">Create account</button>
    </form>

    <x-slot:footer>
        Already have an account?
        <a href="{{ route('login') }}" class="font-medium text-accent hover:underline">Sign in</a>
    </x-slot:footer>
</x-guest-layout>
