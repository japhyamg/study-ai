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
                @endphp
                <li class="px-5 py-3">
                    <div class="font-medium">{{ $i + 1 }}. {{ $q->question }}</div>
                    @if($q->options && is_array($q->options) && count($q->options))
                        <div class="text-xs text-muted mt-1">
                            Your answer: {{ isset($ans) && $ans['given'] !== null ? ($q->options[$ans['given']] ?? $ans['given']) : '—' }}
                            @if(isset($ans) && $ans['correct'])<span class="text-ok"> ✓ correct</span>@else<span class="text-danger"> ✗ incorrect ({{ $q->options[$q->answer] ?? $q->answer }})</span>@endif
                        </div>
                    @else
                        <div class="text-xs text-muted mt-1">Your answer: {{ $ans['given'] ?? '—' }} @if($ans['correct'])✓@else ✗ ({{ $q->answer }})@endif</div>
                    @endif
                    @if($q->explanation)<div class="text-xs text-faint mt-1">{{ $q->explanation }}</div>@endif
                </li>
            @endforeach
        </ol>
    </div>
</x-layouts.studyai>
