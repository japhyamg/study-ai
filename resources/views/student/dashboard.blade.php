<x-layouts.studyai title="Dashboard" subtitle="Welcome back">

    {{-- Stat cards. The .stat classes are the shared dashboard treatment, so
         this reads the same as the teacher and admin screens. --}}
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <a href="{{ route('student.subjects') }}" class="stat block transition-colors hover:border-accent/40">
            <div class="stat-label">Subjects</div>
            <div class="stat-value">{{ $stats['subjects'] }}</div>
            <div class="stat-meta">What you are taught</div>
        </a>

        <a href="{{ route('student.study.index') }}" class="stat block transition-colors hover:border-accent/40">
            <div class="stat-label">Cards due</div>
            <div class="stat-value">{{ $stats['dueFlashcards'] }}</div>
            <div class="stat-meta">Ready to review</div>
        </a>

        <a href="{{ route('student.exams') }}" class="stat block transition-colors hover:border-accent/40">
            <div class="stat-label">Upcoming exams</div>
            <div class="stat-value">{{ $stats['upcomingExams'] }}</div>
            <div class="stat-meta">Not yet taken</div>
        </a>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card title="Exams you can take" :padded="false">
                <x-slot:actions>
                    <a href="{{ route('student.exams') }}" class="text-xs text-accent">All exams</a>
                </x-slot:actions>

                @if ($availableExams->isEmpty())
                    <div class="px-5 py-8 text-center text-sm text-faint">
                        Nothing to sit right now.
                    </div>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($availableExams->take(5) as $exam)
                            <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-ink">{{ $exam->title }}</p>
                                    <p class="mt-0.5 text-xs text-faint">
                                        {{ $exam->subject?->name ?? 'General' }}
                                        · <span class="tnum">{{ $exam->questions_count }}</span>
                                        {{ Str::plural('question', $exam->questions_count) }}
                                    </p>
                                </div>

                                <form method="POST" action="{{ route('student.exams.start', $exam) }}">
                                    @csrf
                                    <x-ui.button type="submit" size="sm">Start</x-ui.button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>

            <x-ui.card title="Recent results" :padded="false">
                @if ($recentAttempts->isEmpty())
                    <div class="px-5 py-8 text-center text-sm text-faint">
                        Nothing sat yet.
                    </div>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($recentAttempts as $attempt)
                            <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                                <a href="{{ route('student.exams.result', [$attempt->exam, $attempt]) }}"
                                   class="min-w-0 text-sm text-ink hover:text-accent">
                                    {{ $attempt->exam?->title ?? 'Exam' }}
                                </a>

                                <span class="text-xs {{ $attempt->passed ? 'text-success' : 'text-danger' }}">
                                    <span class="tnum">{{ $attempt->percentage }}</span>% ·
                                    {{ $attempt->passed ? 'Passed' : 'Not passed' }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card title="Your subjects" :padded="false">
                @if ($subjects->isEmpty())
                    <div class="px-5 py-8 text-center text-sm text-faint">
                        No subjects assigned yet.
                    </div>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($subjects as $assignment)
                            <li>
                                <a href="{{ route('student.subjects.show', $assignment->subject) }}"
                                   class="flex items-center justify-between gap-3 px-5 py-3 transition-colors hover:bg-surface-sunk/50">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm text-ink">{{ $assignment->subject->name }}</p>
                                        @if ($assignment->teacher)
                                            <p class="truncate text-xs text-faint">{{ $assignment->teacher->name }}</p>
                                        @endif
                                    </div>
                                    <x-icon name="chevron-right" class="shrink-0 text-faint" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>

            @if ($upcomingExams->isNotEmpty())
                <x-ui.card title="Coming up" :padded="false">
                    <ul class="divide-y divide-line">
                        @foreach ($upcomingExams as $exam)
                            <li class="px-5 py-3">
                                <p class="text-sm text-ink">{{ $exam->title }}</p>
                                <p class="mt-0.5 text-xs text-faint">
                                    {{ $exam->start_time?->format('j M Y') ?? 'Anytime' }}
                                    @if ($exam->duration)
                                        · {{ $exam->duration }} min
                                    @endif
                                </p>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif
        </div>
    </div>
</x-layouts.studyai>
