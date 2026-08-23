{{--
    Shared shell for HTTP error pages.

    Errors are where a user is most likely to be confused, so these stay
    plain: what happened, and the way back. No stack traces, no exception
    class names, no framework branding — in production those tell the reader
    nothing and tell an attacker something.

    The guest layout already renders $title as the heading and $description
    beneath it, so this only adds the status code, the optional reference and
    the actions.

    $code      — HTTP status
    $title     — one short line (rendered by the guest layout)
    $message   — one or two sentences
    $reference — optional support code, matching the logged exception
--}}
<x-guest-layout :title="$title" :description="$message">
    <div class="space-y-4 text-center">
        <p class="tnum text-xs font-medium tracking-widest text-faint">Error {{ $code }}</p>

        @isset($reference)
            @if ($reference)
                <p class="text-xs text-faint">
                    Reference <span class="tnum font-medium text-muted">{{ $reference }}</span> —
                    quote this if you contact support.
                </p>
            @endif
        @endisset

        <div class="flex flex-wrap items-center justify-center gap-2 pt-1">
            <a href="{{ url('/') }}" class="btn btn-primary btn-sm">Go to the dashboard</a>
            <button type="button" class="btn btn-ghost btn-sm" onclick="history.back()">Go back</button>
        </div>
    </div>
</x-guest-layout>
