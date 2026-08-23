@php $total = $materials->flatten()->count(); @endphp

<x-layouts.studyai title="Study guides"
                   subtitle="Everything your teachers have published for your subjects.">

    {{-- The review queue cuts across every guide, so it sits above them
         rather than inside any one subject. --}}
    <a href="{{ route('student.study.session') }}"
       class="surface mb-6 flex items-center justify-between gap-3 p-4 transition-colors hover:border-accent/40">
        <div class="min-w-0">
            <p class="font-medium text-ink">Review due cards</p>
            <p class="mt-0.5 text-xs text-faint">
                @if ($dueCount === 0)
                    Nothing due right now
                @else
                    <span class="tnum">{{ $dueCount }}</span>
                    {{ Str::plural('card', $dueCount) }} ready across all your guides
                @endif
            </p>
        </div>

        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-surface-sunk text-muted">
            <x-icon name="sparkles" />
        </span>
    </a>

    @if ($total === 0)
        <x-ui.empty icon="document" title="No study guides yet"
                    message="When a teacher publishes material for one of your subjects, it appears here." />
    @else
        @foreach ($materials as $subject => $guides)
            <div class="mb-6">
                <h2 class="mb-2 text-sm font-medium text-muted">{{ $subject }}</h2>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($guides as $guide)
                        <a href="{{ route('student.study.hub', $guide) }}"
                           class="surface p-4 transition-colors hover:border-accent/40">
                            <p class="font-medium text-ink">{{ $guide->title }}</p>

                            {{-- What is actually inside, so the student knows
                                 whether there is anything to practise. --}}
                            <p class="mt-1.5 text-xs text-faint">
                                Guide
                                @if ($guide->flashcards_count)
                                    · <span class="tnum">{{ $guide->flashcards_count }}</span>
                                    {{ Str::plural('card', $guide->flashcards_count) }}
                                @endif
                                @if ($guide->questions_count)
                                    · <span class="tnum">{{ $guide->questions_count }}</span>
                                    {{ Str::plural('question', $guide->questions_count) }}
                                @endif
                            </p>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    @endif
</x-layouts.studyai>
