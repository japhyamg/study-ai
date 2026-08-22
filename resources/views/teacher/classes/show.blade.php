<x-layouts.studyai title="{{ $class->name }}">
    <a href="{{ route('teacher.classes.index') }}" class="text-xs text-accent">← My classes</a>
    <h2 class="font-display text-xl mt-1">{{ $class->name }}</h2>
    <div class="muted text-sm">{{ $class->subject?->name ?? 'General' }} · {{ $class->term?->name ?? '' }} · {{ $class->enrollments_count }} students</div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
        <div class="surface p-5 md:col-span-2">
            <div class="font-medium text-ink mb-3">Students</div>
            @if($class->enrollments->isEmpty())
                <div class="empty">No students enrolled.</div>
            @else
                <ul class="divide-y">
                    @foreach($class->enrollments as $e)
                        <li class="py-2 flex items-center justify-between text-sm">
                            <span>{{ $e->user->name }}</span>
                            <span class="faint text-xs">{{ $e->user->email }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="space-y-6">
            <div class="surface p-5">
                <div class="font-medium text-ink mb-3">Materials</div>
                @forelse($class->materials as $m)
                    <div class="py-1 text-sm"><a href="{{ route('teacher.materials.show', $m) }}" class="hover:underline">{{ $m->title }}</a></div>
                @empty
                    <div class="faint text-sm">None.</div>
                @endforelse
            </div>
            <div class="surface p-5">
                <div class="font-medium text-ink mb-3">Exams</div>
                @forelse($class->exams as $ex)
                    <div class="py-1 text-sm flex justify-between">
                        <a href="{{ route('teacher.exams.show', $ex) }}" class="hover:underline">{{ $ex->title }}</a>
                        <span class="faint text-xs">{{ $ex->attempts_count }} attempts</span>
                    </div>
                @empty
                    <div class="faint text-sm">None.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-layouts.studyai>
