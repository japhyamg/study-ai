@php
    /**
     * Question bank — subject picker.
     *
     * A bank is a subject's accumulated work, so the landing page is the list
     * of subjects rather than every question at once. Several hundred
     * questions across four subjects is not a list anyone reads; the counts
     * make the shape visible before you commit to opening one.
     */
@endphp

<x-layouts.studyai title="Question bank"
                   subtitle="Approved questions, collected by subject.">

    @if ($subjects->isEmpty())
        <x-ui.empty icon="database" title="No subjects assigned"
                    message="You are not assigned to a subject yet, so there is no bank to show. An administrator assigns subjects under Academics." />
    @else
        <p class="mb-4 text-xs text-faint">
            {{ number_format($total) }} {{ Str::plural('question', $total) }} across
            {{ $subjects->count() }} {{ Str::plural('subject', $subjects->count()) }}.
            Questions join a bank when an admin approves the study guide they came from.
        </p>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($subjects as $subject)
                @php $count = (int) ($counts[$subject->id] ?? 0); @endphp

                <a href="{{ route('teacher.question-bank.show', $subject) }}"
                   class="surface flex items-center justify-between gap-3 p-4 transition-colors hover:border-accent/40">
                    <div class="min-w-0">
                        <p class="truncate font-medium text-ink">{{ $subject->name }}</p>
                        <p class="mt-0.5 text-xs text-faint">
                            @if ($count === 0)
                                Nothing banked yet
                            @else
                                <span class="tnum">{{ number_format($count) }}</span>
                                {{ Str::plural('question', $count) }}
                            @endif
                        </p>
                    </div>

                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-surface-sunk text-muted">
                        <x-icon name="chevron-right" />
                    </span>
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.studyai>
