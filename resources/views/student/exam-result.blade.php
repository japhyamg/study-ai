<x-layouts.studyai title="Exam Result: {{ $exam->title }}">
    <div class="surface p-6 text-center">
        <div class="text-4xl font-bold {{ $attempt->passed ? 'text-ok' : 'text-danger' }}">{{ $attempt->percentage }}%</div>
        <div class="text-sm text-muted mt-1">{{ $attempt->passed ? 'Passed' : 'Failed' }} · {{ $attempt->score }}/{{ $attempt->max_score }} points</div>
        <a href="{{ route('student.exams') }}" class="mt-4 inline-block text-xs text-accent">← Back to exams</a>
    </div>

    <div class="surface mt-6">
        <div class="px-5 py-3 border-b font-semibold text-ink">Review</div>
        <ol class="divide-y text-sm">
            @foreach($questions as $i => $q)
                @php
                    $ans = collect($attempt->answers)->firstWhere('question_id', $q->id);

                    // Answers are stored as text, so they read back directly.
                    $given = $ans['given'] ?? null;
                    $wasCorrect = (bool) ($ans['correct'] ?? false);
                    $correct = $paper->correctAnswer($q);
                @endphp
                <li class="px-5 py-3">
                    <div class="font-medium">{{ $i + 1 }}. {{ $q->question }}</div>
                    <div class="text-xs text-muted mt-1">
                        Your answer: {{ $given !== null && $given !== '' ? $given : '—' }}
                        @if($wasCorrect)
                            <span class="text-ok"> ✓ correct</span>
                        @else
                            <span class="text-danger"> ✗ incorrect ({{ $correct }})</span>
                        @endif
                    </div>
                    @if($q->explanation)<div class="text-xs text-faint mt-1">{{ $q->explanation }}</div>@endif
                </li>
            @endforeach
        </ol>
    </div>
</x-layouts.studyai>
