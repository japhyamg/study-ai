@php
    /**
     * A teacher's AI allowance and what it went on.
     *
     * The allowance is a calendar-month window rather than a stored counter:
     * usage is measured from the 1st, so it resets on its own. The page says
     * so explicitly, because "resets monthly" is otherwise a promise the
     * reader has to take on trust.
     */
    $limit = (int) $limits['monthlyLimit'];
    $used = (int) $limits['usedThisMonth'];
    $remaining = (int) $limits['remaining'];
    $enabled = (bool) $limits['isEnabled'];

    $percent = $limit > 0 ? min(100, (int) round($used / $limit * 100)) : 0;

    // Three bands, so the bar reads as a status and not just a quantity.
    $tone = match (true) {
        ! $enabled => 'muted',
        $percent >= 90 => 'danger',
        $percent >= 70 => 'warning',
        default => 'success',
    };

    // Full literal class names: Tailwind scans the source, so an interpolated
    // class like "text-{$tone}" is never generated and silently renders unstyled.
    $barColour = [
        'danger' => 'bg-danger',
        'warning' => 'bg-warning',
        'success' => 'bg-success',
        'muted' => 'bg-line-strong',
    ][$tone];

    $textColour = [
        'danger' => 'text-danger',
        'warning' => 'text-warning',
        'success' => 'text-success',
        'muted' => 'text-muted',
    ][$tone];
@endphp

<x-layouts.studyai title="AI usage"
                   subtitle="Your token allowance for {{ $since->format('F Y') }}.">

    {{-- ── Allowance ── --}}
    <div class="surface p-5">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs text-faint">Used this month</p>
                <p class="mt-1">
                    <span class="tnum text-2xl font-semibold text-ink">{{ number_format($used) }}</span>
                    <span class="text-sm text-muted">of {{ number_format($limit) }} tokens</span>
                </p>
            </div>

            <div class="text-end">
                <p class="text-xs text-faint">Remaining</p>
                <p class="tnum mt-1 text-lg font-medium {{ $textColour }}">
                    {{ number_format($remaining) }}
                </p>
            </div>
        </div>

        <div class="mt-3 h-2 overflow-hidden rounded-full bg-surface-sunk">
            <div class="h-full rounded-full {{ $barColour }} transition-all" style="width: {{ $percent }}%"></div>
        </div>

        <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs text-faint">
            <span class="tnum">{{ $percent }}% used</span>
            <span>Resets on {{ $resetsOn->format('j F') }}</span>
        </div>

        @if (! $enabled)
            <div class="alert alert-info mt-4" role="status">
                <x-icon name="info" class="mt-px flex-none" />
                <span>
                    Your allowance is not being enforced, so generation will keep working past the limit.
                    Usage is still recorded here.
                </span>
            </div>
        @elseif ($remaining === 0)
            <div class="alert alert-danger mt-4" role="alert">
                <x-icon name="alert-circle" class="mt-px flex-none" />
                <span>
                    You've used your allowance for this month. Generation will fail until it resets on
                    {{ $resetsOn->format('j F') }} — ask an administrator if you need more before then.
                </span>
            </div>
        @elseif ($percent >= 90)
            <div class="alert alert-warn mt-4" role="alert">
                <x-icon name="alert-circle" class="mt-px flex-none" />
                <span>
                    You have {{ number_format($remaining) }} tokens left — roughly
                    {{ max(1, (int) floor($remaining / 8000)) }} more generation{{ $remaining >= 16000 ? 's' : '' }}.
                </span>
            </div>
        @endif
    </div>

    {{-- ── Where it went ── --}}
    <div class="mt-5">
        <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="text-sm font-semibold text-ink">Spend by study guide</h2>
            @if ($totalCost > 0)
                <span class="text-xs text-faint">Estimated cost ${{ number_format($totalCost, 4) }}</span>
            @endif
        </div>

        @if ($rows->isEmpty())
            <x-ui.empty icon="gauge" title="Nothing generated yet this month"
                        message="Token usage appears here once you generate a study guide, flashcards or a quiz." />
        @else
            <div class="surface table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Study guide</th>
                            <th class="text-end">Runs</th>
                            <th class="text-end">Tokens</th>
                            <th class="hidden text-end sm:table-cell">Share</th>
                            <th class="hidden md:table-cell">Last used</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php
                                $share = $used > 0 ? (int) round($row['tokens'] / $used * 100) : 0;
                            @endphp
                            <tr>
                                <td class="font-medium text-ink">
                                    @if ($row['material'])
                                        <a href="{{ route('learning.materials.show', $row['material']) }}"
                                           class="text-accent hover:underline">{{ $row['material']->title }}</a>
                                    @else
                                        {{-- The material was deleted, or the spend predates
                                             per-material attribution. --}}
                                        <span class="text-muted">Other activity</span>
                                    @endif
                                </td>
                                <td class="tnum text-end text-muted">{{ $row['runs'] }}</td>
                                <td class="tnum text-end text-ink">{{ number_format($row['tokens']) }}</td>
                                <td class="tnum hidden text-end text-muted sm:table-cell">{{ $share }}%</td>
                                <td class="hidden text-xs text-faint md:table-cell">
                                    {{ $row['lastUsed']?->diffForHumans() ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ── By kind of generation ── --}}
    @if ($byOperation->isNotEmpty())
        <div class="mt-5">
            <h2 class="mb-3 text-sm font-semibold text-ink">Spend by type</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($byOperation as $op)
                    <div class="surface p-4">
                        <p class="text-xs text-faint">{{ Str::headline($op['operation']) }}</p>
                        <p class="tnum mt-1 text-lg font-medium text-ink">{{ number_format($op['tokens']) }}</p>
                        <p class="text-xs text-muted">{{ $op['runs'] }} {{ Str::plural('run', $op['runs']) }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <p class="mt-5 text-xs text-faint">
        Your allowance is set by the platform administrator and covers the calendar month.
        Counts include every generation and regeneration, whether or not the result was kept.
    </p>
</x-layouts.studyai>
