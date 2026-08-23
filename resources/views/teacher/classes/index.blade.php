@php $userId = auth()->id(); @endphp

<x-layouts.studyai title="My classes" subtitle="Classes you teach or lead">

    @if ($classes->isEmpty())
        <x-ui.empty icon="users" title="No classes yet"
                    message="You'll see a class here once an administrator makes you its form teacher or assigns you a subject in it." />
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($classes as $c)
                @php
                    $mine = $c->subjectAssignments->where('teacher_id', $userId);
                    $isForm = $c->form_teacher_id === $userId;
                @endphp

                <a href="{{ route('teacher.classes.show', $c) }}"
                   class="surface p-4 transition-colors hover:border-line-strong">
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="text-sm font-semibold text-ink">{{ $c->fullName() }}</h2>
                        @if ($isForm)
                            <span class="badge badge-accent">Form teacher</span>
                        @endif
                    </div>

                    @if ($c->stream)
                        <p class="mt-0.5 text-xs text-faint">{{ $c->stream }}</p>
                    @endif

                    @if ($mine->isNotEmpty())
                        <div class="mt-2.5 flex flex-wrap gap-1">
                            @foreach ($mine as $assignment)
                                <span class="badge">{{ $assignment->subject?->name }}</span>
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-3 flex items-center gap-3 text-xs text-muted">
                        <span class="tnum">{{ $c->enrollments_count }} students</span>
                        <span class="text-faint">·</span>
                        <span class="tnum">{{ $c->capacity }} seats</span>
                    </div>
                </a>
            @endforeach
        </div>

        @if ($classes->hasPages())
            <div class="mt-5">{{ $classes->links() }}</div>
        @endif
    @endif
</x-layouts.studyai>
