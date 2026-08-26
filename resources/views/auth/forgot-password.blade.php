<x-guest-layout title="Reset your password"
                description="We'll email you a link to choose a new password.">

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <x-ui.field label="Email address" name="email" required>
            <input id="email" name="email" type="email" class="input"
                   value="{{ old('email') }}" required autofocus autocomplete="username">
        </x-ui.field>

        <button type="submit" class="btn btn-primary btn-block btn-lg">Email reset link</button>
    </form>

    <x-slot:footer>
        <a href="{{ route('login') }}" class="font-medium text-accent hover:underline">Back to sign in</a>
    </x-slot:footer>
</x-guest-layout>
