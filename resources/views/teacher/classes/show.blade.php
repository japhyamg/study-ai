@php
    $userId = auth()->id();
    $mine = $class->subjectAssignments->where('teacher_id', $userId);
@endphp

<x-layouts.studyai :title="$class->fullName()"
                   :subtitle="trim(($class->classLevel?->name ?? '').($class->stream ? ' · '.$class->stream : ''))">
    <x-slot:actions>
        <a href="{{ route('teacher.classes.index') }}" class="btn btn-outline btn-sm">All classes</a>
    </x-slot:actions>

    <div class="mb-5 grid gap-4 sm:grid-cols-3">
        <x-ui.stat label="Students" :value="$class->enrollments->count()" icon="users" />
        <x-ui.stat label="Subjects you teach" :value="$mine->count()"
                   :meta="$mine->pluck('subject.name')->filter()->join(', ') ?: null" icon="book" />
        <x-ui.stat label="Form teacher" :value="$class->formTeacher?->name ?? '—'"
                   :meta="$class->form_teacher_id === $userId ? 'That\'s you' : null" icon="presentation" />
    </div>

    <div class="grid gap-5 lg:grid-cols-3">
        {{-- Students --}}
        <div class="lg:col-span-2">
            <x-ui.card title="Students" :subtitle="$class->enrollments->count().' enrolled'">
                @if ($class->enrollments->isEmpty())
                    <x-ui.empty message="No students enrolled in this class yet." />
                @else
                    <ul class="divide-y">
                        @foreach ($class->enrollments->sortBy(fn ($e) => $e->user?->name) as $enrollment)
                            <li class="flex items-center gap-2.5 py-2">
                                <span class="avatar avatar-sm">{{ $enrollment->user?->initials() }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm text-ink">{{ $enrollment->user?->name }}</p>
                                    <p class="truncate text-xs text-faint">{{ $enrollment->user?->email }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>

        <div class="space-y-5">
            {{-- Who teaches what --}}
            <x-ui.card title="Subjects">
                @if ($class->subjectAssignments->isEmpty())
                    <x-ui.empty message="No subjects assigned yet." />
                @else
                    <ul class="divide-y">
                        @foreach ($class->subjectAssignments->sortBy(fn ($a) => $a->subject?->name) as $assignment)
                            <li class="flex items-center justify-between gap-2 py-2">
                                <span class="truncate text-sm text-ink">{{ $assignment->subject?->name }}</span>
                                <span class="truncate text-xs {{ $assignment->teacher_id === $userId ? 'font-medium text-accent' : 'text-faint' }}">
                                    {{ $assignment->teacher_id === $userId ? 'You' : $assignment->teacher?->name }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>

            <x-ui.card title="Materials">
                @forelse ($class->materials as $material)
                    <div class="py-1.5 text-sm">
                        <a href="{{ route('teacher.materials.show', $material) }}" class="text-ink hover:text-accent">
                            {{ $material->title }}
                        </a>
                    </div>
                @empty
                    <x-ui.empty message="No materials yet." />
                @endforelse
            </x-ui.card>

            <x-ui.card title="Exams">
                @forelse ($class->exams as $exam)
                    <div class="flex items-center justify-between gap-2 py-1.5 text-sm">
                        <a href="{{ route('teacher.exams.show', $exam) }}" class="truncate text-ink hover:text-accent">
                            {{ $exam->title }}
                        </a>
                        <span class="flex-none text-xs text-faint tnum">{{ $exam->attempts_count }} attempts</span>
                    </div>
                @empty
                    <x-ui.empty message="No exams yet." />
                @endforelse
            </x-ui.card>
        </div>
    </div>
</x-layouts.studyai>
