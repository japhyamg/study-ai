<x-layouts.studyai title="My Classes">
    <div class="flex items-center justify-between mb-4">
        <span class="section-title">My Classes</span>
    </div>

    @if($classes->isEmpty())
        <div class="empty">You aren't assigned to any classes yet.</div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($classes as $c)
                <a href="{{ route('teacher.classes.show', $c) }}" class="surface p-5 hover:border-line-strong">
                    <div class="font-display text-lg">{{ $c->name }}</div>
                    <div class="muted text-sm mt-1">{{ $c->subject?->name ?? 'General' }} · {{ $c->term?->name ?? '' }}</div>
                    <div class="flex gap-4 mt-3 text-sm muted">
                        <span>{{ $c->enrollments_count }} students</span>
                        <span>{{ $c->materials->count() }} materials</span>
                        <span>{{ $c->exams->count() }} exams</span>
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-4">{{ $classes->links() }}</div>
    @endif
</x-layouts.studyai>
