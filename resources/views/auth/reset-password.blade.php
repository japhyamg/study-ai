<x-guest-layout title="Choose a new password">

    <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-ui.field label="Email address" name="email" required>
            <input id="email" name="email" type="email" class="input"
                   value="{{ old('email', $request->email) }}" required autofocus autocomplete="username">
        </x-ui.field>

        <x-ui.field label="New password" name="password" required hint="At least 8 characters.">
            <input id="password" name="password" type="password" class="input" required autocomplete="new-password">
        </x-ui.field>

        <x-ui.field label="Confirm password" name="password_confirmation" required>
            <input id="password_confirmation" name="password_confirmation" type="password"
                   class="input" required autocomplete="new-password">
        </x-ui.field>

        <button type="submit" class="btn btn-primary btn-block btn-lg">Reset password</button>
    </form>
</x-guest-layout>
