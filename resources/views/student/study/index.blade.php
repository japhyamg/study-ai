<x-layouts.studyai title="Study">
    <div class="section-title mb-1">Study Mode</div>
    <p class="muted text-sm mb-5">Pick a set and run a guided review. Cards advance with spaced-repetition as you rate them.</p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <a href="{{ route('student.study.session') }}" class="surface p-5 hover:border-line-strong">
            <div class="font-display text-lg">All due cards</div>
            <div class="muted text-sm mt-1">{{ $dueCount }} card{{ $dueCount === 1 ? '' : 's' }} ready to review</div>
            <div class="mt-3 text-accent text-sm">Start session →</div>
        </a>
        @forelse($materials as $m)
            <a href="{{ route('student.study.hub', $m) }}" class="surface p-5 hover:border-line-strong">
                <div class="font-display text-lg">{{ $m->title }}</div>
                <div class="muted text-sm mt-1">{{ $m->flashcards_count }} card{{ $m->flashcards_count === 1 ? '' : 's' }} · {{ $m->subject?->name ?? 'General' }}</div>
                <div class="mt-3 text-accent text-sm">Study this set →</div>
            </a>
        @empty
            <div class="empty md:col-span-2">No materials with flashcards yet.</div>
        @endforelse
    </div>
</x-layouts.studyai>
