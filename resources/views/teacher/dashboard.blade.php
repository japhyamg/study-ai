<x-layouts.studyai title="Teacher Dashboard">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="surface p-5 lg:col-span-2">
            <div class="font-semibold text-ink mb-3">My Classes</div>
            <ul class="divide-y text-sm">
                @forelse($myClasses as $c)
                    <li class="py-2 flex items-center justify-between">
                        <span>{{ $c->name }} <span class="text-faint">· {{ $c->enrollments_count }} students</span></span>
                    </li>
                @empty
                    <li class="py-2 text-faint">No classes assigned.</li>
                @endforelse
            </ul>
        </div>
        <div class="surface p-5">
            <div class="font-semibold text-ink mb-3">Pending Materials</div>
            <ul class="divide-y text-sm">
                @forelse($pendingMaterials as $m)
                    <li class="py-2">{{ $m->title }} <span class="text-xs text-faint">· {{ $m->classRoom?->name ?? '—' }}</span></li>
                @empty
                    <li class="py-2 text-faint">Nothing to review.</li>
                @endforelse
            </ul>
            @if($pendingMaterials->count())
                <a href="{{ route('teacher.materials.review') }}" class="mt-3 inline-block text-xs text-accent">Review all →</a>
            @endif
        </div>
    </div>

    <div class="surface p-5 mt-6">
        <div class="font-semibold text-ink mb-3">Recent Exams</div>
        <ul class="divide-y text-sm">
            @forelse($myExams as $e)
                <li class="py-2 flex items-center justify-between">
                    <a href="{{ route('teacher.exams.show', $e) }}" class="hover:underline">{{ $e->title }}</a>
                    <span class="text-xs px-2 py-0.5 rounded {{ $e->status === 'published' ? 'bg-green-100 text-ok' : 'bg-paper-sunk text-muted' }}">{{ $e->status }}</span>
                </li>
            @empty
                <li class="py-2 text-faint">No exams yet.</li>
            @endforelse
        </ul>
        <a href="{{ route('teacher.exams.create') }}" class="mt-3 inline-block px-3 py-1 btn btn-primary text-sm">New Exam</a>
    </div>
</x-layouts.studyai>
