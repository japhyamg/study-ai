<x-layouts.studyai title="Subjects" subtitle="What you are taught this session.">
    @if ($assignments->isEmpty())
        <x-ui.empty icon="book" title="No subjects yet"
                    message="Once your class is given its subjects, they appear here." />
    @else
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($assignments as $assignment)
                <a href="{{ route('student.subjects.show', $assignment->subject) }}"
                   class="surface flex items-center justify-between gap-3 p-4 transition-colors hover:border-accent/40">
                    <div class="min-w-0">
                        <p class="truncate font-medium text-ink">{{ $assignment->subject->name }}</p>

                        <p class="mt-0.5 truncate text-xs text-faint">
                            @if ($assignment->teacher)
                                {{ $assignment->teacher->name }}
                            @else
                                No teacher assigned yet
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
