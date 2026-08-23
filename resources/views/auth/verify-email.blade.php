<x-guest-layout title="Verify your email"
                description="We've sent a verification link to your inbox.">

    @if (session('status') === 'verification-link-sent')
        <div class="alert alert-ok mb-4" role="status">
            <x-icon name="check-circle" class="mt-px flex-none" />
            <span>A new verification link has been sent.</span>
        </div>
    @endif

    <p class="mb-4 text-sm text-muted">
        Click the link in the email to finish setting up your account. Didn't get it?
    </p>

    <div class="space-y-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="btn btn-primary btn-block">Resend verification email</button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-ghost btn-block btn-sm">Sign out</button>
        </form>
    </div>
</x-guest-layout>
