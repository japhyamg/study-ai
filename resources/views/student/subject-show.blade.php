<x-layouts.studyai :title="$subject->name"
                   :back-to="route('student.subjects')" back-label="All subjects">

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-ui.card title="Exams" :padded="false">
                @if ($exams->isEmpty())
                    <div class="px-5 py-8 text-center text-sm text-faint">
                        No exams set for this subject yet.
                    </div>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($exams as $exam)
                            @php $attempt = $attempts[$exam->id] ?? null; @endphp

                            <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-ink">{{ $exam->title }}</p>
                                    <p class="mt-0.5 text-xs text-faint">
                                        <span class="tnum">{{ $exam->questions_count }}</span>
                                        {{ Str::plural('question', $exam->questions_count) }}
                                        @if ($exam->duration)
                                            · {{ $exam->duration }} min
                                        @endif
                                    </p>
                                </div>

                                @if ($attempt)
                                    <a href="{{ route('student.exams.result', [$exam, $attempt]) }}"
                                       class="text-xs {{ $attempt->passed ? 'text-success' : 'text-danger' }}">
                                        <span class="tnum">{{ $attempt->percentage }}</span>% ·
                                        {{ $attempt->passed ? 'Passed' : 'Not passed' }}
                                    </a>
                                @else
                                    <form method="POST" action="{{ route('student.exams.start', $exam) }}">
                                        @csrf
                                        <x-ui.button type="submit" size="sm">Start</x-ui.button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>

        <div>
            <x-ui.card title="Taught by">
                @if ($assignment->teacher)
                    <div class="flex items-center gap-3">
                        <span class="avatar shrink-0">{{ $assignment->teacher->initials() }}</span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-ink">{{ $assignment->teacher->name }}</p>
                            <p class="truncate text-xs text-faint">{{ $assignment->classArm?->fullName() }}</p>
                        </div>
                    </div>
                @else
                    <p class="text-sm text-faint">No teacher assigned yet.</p>
                @endif
            </x-ui.card>
        </div>
    </div>
</x-layouts.studyai>
