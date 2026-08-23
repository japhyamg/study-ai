@props(['user'])

@php
    /**
     * AI allowance indicator for the topbar.
     *
     * Only meaningful for people who can spend tokens, so it renders nothing
     * for students. The allowance is a calendar-month window — usage is summed
     * from the 1st, so it resets on its own with no job to run.
     */
    $limits = $user ? app(App\Services\TokenLimitService::class)->getTeacherTokenLimitCached($user->id) : null;
@endphp

@if ($limits)
    @php
        $limit = max(1, (int) $limits['monthlyLimit']);
        $used = (int) $limits['usedThisMonth'];
        $remaining = (int) $limits['remaining'];
        $enabled = (bool) $limits['isEnabled'];

        $percent = min(100, (int) round($used / $limit * 100));

        // Bands, so the colour reports a state rather than just a number.
        // An unenforced allowance stays neutral: it is information, not a warning.
        $tone = match (true) {
            ! $enabled => 'muted',
            $percent >= 90 => 'danger',
            $percent >= 70 => 'warning',
            default => 'ok',
        };

        // Literal class names throughout: Tailwind scans source text, so an
        // interpolated class is never generated and renders unstyled.
        [$textClass, $barClass] = match ($tone) {
            'danger' => ['text-danger', 'bg-danger'],
            'warning' => ['text-warning', 'bg-warning'],
            'ok' => ['text-muted', 'bg-success'],
            default => ['text-faint', 'bg-line-strong'],
        };

        $compact = fn (int $n) => match (true) {
            $n >= 1_000_000 => rtrim(rtrim(number_format($n / 1_000_000, 1), '0'), '.').'M',
            $n >= 1_000 => rtrim(rtrim(number_format($n / 1_000, 1), '0'), '.').'k',
            default => (string) $n,
        };

        $title = $enabled
            ? number_format($used).' of '.number_format($limit).' AI tokens used this month · '
                .number_format($remaining).' left · resets '.now()->addMonthNoOverflow()->startOfMonth()->format('j M')
            : number_format($used).' AI tokens used this month · no limit enforced';
    @endphp

    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
        <button type="button"
                class="btn btn-ghost btn-icon relative"
                @click="open = ! open"
                :aria-expanded="open.toString()"
                aria-haspopup="dialog"
                title="{{ $title }}"
                aria-label="{{ $title }}">
            <x-icon name="gauge" class="{{ $textClass }}" />

            {{-- A dot rather than a number: the topbar is not the place for a
                 figure, only for whether attention is needed. --}}
            @if ($enabled && $percent >= 70)
                <span class="absolute end-1 top-1 h-1.5 w-1.5 rounded-full {{ $barClass }}"></span>
            @endif
        </button>

        <div x-show="open" x-cloak
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="popover absolute end-0 mt-1 w-64 p-3"
             role="dialog"
             aria-label="AI token allowance">

            <div class="flex items-baseline justify-between gap-2">
                <span class="text-xs text-faint">AI tokens this month</span>
                @if ($enabled)
                    <span class="tnum text-xs font-medium {{ $textClass }}">{{ $percent }}%</span>
                @endif
            </div>

            <p class="tnum mt-1 text-sm text-ink">
                <span class="font-semibold">{{ $compact($used) }}</span>
                <span class="text-muted">/ {{ $enabled ? $compact($limit) : '∞' }}</span>
            </p>

            @if ($enabled)
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-surface-sunk">
                    <div class="h-full rounded-full {{ $barClass }} transition-all"
                         style="width: {{ $percent }}%"></div>
                </div>

                <p class="mt-2 text-xs text-faint">
                    @if ($remaining === 0)
                        <span class="text-danger">Allowance used.</span>
                        Generation will fail until {{ now()->addMonthNoOverflow()->startOfMonth()->format('j M') }}.
                    @else
                        {{ number_format($remaining) }} left · resets
                        {{ now()->addMonthNoOverflow()->startOfMonth()->format('j M') }}
                    @endif
                </p>
            @else
                <p class="mt-2 text-xs text-faint">
                    No limit is being enforced. Usage is still recorded.
                </p>
            @endif
        </div>
    </div>
@endif
