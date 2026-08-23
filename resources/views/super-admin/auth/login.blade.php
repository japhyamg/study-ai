<x-guest-layout title="Platform sign in"
                description="Super administrator access"
                pitch-title="StudyAI platform console"
                pitch-body="Manage schools, AI providers and platform-wide usage from one place."
                :pitch-points="['Provision and suspend schools', 'Configure AI providers', 'Monitor token consumption']">

    <div class="alert alert-info mb-4">
        <x-icon name="shield" class="mt-px flex-none" />
        <span class="text-xs">This console is for platform staff. School users sign in on their school's own address.</span>
    </div>

    <form method="POST" action="{{ route('super-admin.login.store') }}" class="space-y-4">
        @csrf

        <x-ui.field label="Email address" name="email" required>
            <input id="email" name="email" type="email" class="input"
                   value="{{ old('email') }}" required autofocus autocomplete="username">
        </x-ui.field>

        <x-ui.field label="Password" name="password" required>
            <input id="password" name="password" type="password" class="input"
                   required autocomplete="current-password">
        </x-ui.field>

        <label for="remember_me" class="inline-flex cursor-pointer items-center gap-2 text-sm text-muted">
            <input id="remember_me" name="remember" type="checkbox" class="checkbox">
            Keep me signed in
        </label>

        <button type="submit" class="btn btn-primary btn-block btn-lg">Sign in</button>
    </form>
</x-guest-layout>
