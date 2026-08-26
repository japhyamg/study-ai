<x-guest-layout title="Confirm your password"
                description="This is a secure area — please confirm your password to continue.">

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4">
        @csrf

        <x-ui.field label="Password" name="password" required>
            <input id="password" name="password" type="password" class="input"
                   required autofocus autocomplete="current-password">
        </x-ui.field>

        <button type="submit" class="btn btn-primary btn-block btn-lg">Confirm</button>
    </form>
</x-guest-layout>
