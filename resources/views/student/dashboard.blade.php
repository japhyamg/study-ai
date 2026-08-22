<x-layouts.studyai title="Dashboard">
    {{-- Greeting --}}
    <div class="mb-5">
        <h1 class="text-xl font-semibold text-ink">Welcome back, {{ explode(' ', auth()->user()->name)[0] }}</h1>
        <p class="text-sm muted">Here's what's happening in your classes.</p>
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="stat">
            <div class="stat-label">Enrolled classes</div>
            <div class="stat-value">{{ $stats['classes'] }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Flashcards due</div>
            <div class="stat-value">
                <a href="{{ route('student.study.index') }}" class="hover:text-accent">{{ $stats['dueFlashcards'] }}</a>
            </div>
        </div>
        <div class="stat">
            <div class="stat-label">Upcoming exams</div>
            <div class="stat-value">{{ $stats['upcomingExams'] }}</div>
        </div>
    </div>

    {{-- Published materials from teachers --}}
    @if($publishedMaterials->isNotEmpty())
    <div class="surface mb-6">
        <div class="px-5 py-3 border-b flex items-center justify-between">
            <span class="font-semibold text-ink">New from your teachers</span>
            <a href="{{ route('student.materials') }}" class="text-xs text-accent">View all →</a>
        </div>
        <div class="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($publishedMaterials as $m)
                <a href="{{ route('student.study.hub', $m) }}" class="border border-line p-4 block hover:border-accent transition-colors" style="border-radius:var(--radius)">
                    <div class="font-medium text-ink">{{ $m->title }}</div>
                    @if($m->subject)<div class="text-xs text-muted mt-0.5">{{ $m->subject->name }}</div>@endif
                    <div class="text-xs text-faint mt-2">{{ $m->flashcards_count }} flashcards · {{ $m->questions_count }} questions</div>
                    <div class="text-[11px] text-faint mt-1">Published {{ $m->published_at?->diffForHumans() ?? $m->created_at->diffForHumans() }}</div>
                </a>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Available exams --}}
    <div class="surface mb-6">
        <div class="px-5 py-3 border-b flex items-center justify-between">
            <span class="font-semibold text-ink">Available exams</span>
            <a href="{{ route('student.exams') }}" class="text-xs text-accent">All exams →</a>
        </div>
        <ul class="divide-y text-sm px-5">
            @forelse($availableExams->take(4) as $e)
                <li class="py-2.5 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="font-medium truncate">{{ $e->title }}</div>
                        <div class="text-xs text-faint">{{ $e->classRoom?->name ?? 'General' }} · {{ $e->questions_count }} questions</div>
                    </div>
                    <form method="POST" action="{{ route('student.exams.start', $e) }}" class="flex-none">@csrf<button class="btn btn-primary btn-sm">Start</button></form>
                </li>
            @empty
                <li class="py-2.5 text-faint">No exams available yet.</li>
            @endforelse
        </ul>
    </div>

    {{-- Upcoming exams (source parity) --}}
    @if($upcomingExams->isNotEmpty())
    <div class="surface mb-6">
        <div class="px-5 py-3 border-b font-semibold text-ink">Upcoming exams</div>
        <ul class="divide-y px-5 text-sm">
            @foreach($upcomingExams as $e)
                <li class="py-3 flex justify-between items-center gap-3">
                    <span class="truncate">{{ $e->title }}</span>
                    <span class="text-xs text-muted flex-none">{{ $e->start_time?->format('M j, Y') ?? 'Anytime' }}{{ $e->duration ? ' · '.$e->duration.'min' : '' }}</span>
                </li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Your classes --}}
        @if($enrollments->isNotEmpty())
        <div class="surface">
            <div class="px-5 py-3 border-b font-semibold text-ink">Your classes</div>
            <div class="grid gap-3 p-5 sm:grid-cols-2">
                @foreach($enrollments as $en)
                    <a href="{{ route('student.classes.show', $en) }}" class="p-4 border border-line block hover:border-accent transition-colors" style="border-radius:var(--radius)">
                        <h3 class="font-medium text-ink">{{ $en->class?->name ?? 'Class' }}</h3>
                        @if($en->class?->subject)<p class="text-sm text-muted">{{ $en->class->subject->name }}</p>@endif
                    </a>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Recent results --}}
        <div class="surface">
            <div class="px-5 py-3 border-b font-semibold text-ink">Recent results</div>
            <ul class="divide-y text-sm px-5">
                @forelse($recentAttempts as $a)
                    <li class="py-2.5 flex items-center justify-between gap-3">
                        <a href="{{ route('student.exams.result', [$a->exam, $a]) }}" class="hover:underline truncate">{{ $a->exam->title }}</a>
                        <span class="text-xs {{ $a->passed ? 'text-ok' : 'text-danger' }} flex-none">{{ $a->percentage }}% · {{ $a->passed ? 'Passed' : 'Failed' }}</span>
                    </li>
                @empty
                    <li class="py-2.5 text-faint">No attempts yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
</x-layouts.studyai>
