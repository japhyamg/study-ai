<x-layouts.studyai title="Material: {{ $material->title }}">
    <a href="{{ url()->previous() }}" class="text-xs text-accent">← Back</a>
    <h2 class="font-semibold text-ink mt-2">{{ $material->title }}</h2>
    <div class="text-xs text-faint">{{ $material->type }} · status: {{ $material->status }} · review: {{ $material->review_status }}</div>

    @if(in_array(auth()->user()->highestRole(), ['teacher','admin','super_admin']))
        <div class="mt-3 flex gap-2">
            <form method="POST" action="{{ route('materials.generate', $material) }}">@csrf<input type="hidden" name="type" value="generate_all"><button class="px-3 py-1 btn btn-primary text-xs">Generate All</button></form>
            <form method="POST" action="{{ route('materials.generate', $material) }}">@csrf<input type="hidden" name="type" value="generate_flashcards"><button class="px-3 py-1 bg-paper-sunk text-white rounded text-xs">Flashcards</button></form>
            <form method="POST" action="{{ route('materials.generate', $material) }}">@csrf<input type="hidden" name="type" value="generate_questions"><button class="px-3 py-1 bg-paper-sunk text-white rounded text-xs">Questions</button></form>
            <form method="POST" action="{{ route('materials.generate', $material) }}">@csrf<input type="hidden" name="type" value="generate_study_guide"><button class="px-3 py-1 bg-paper-sunk text-white rounded text-xs">Study Guide</button></form>
        </div>
    @endif

    @if($material->studyGuide)
        <div class="surface p-5 mt-5">
            <div class="font-semibold text-ink mb-2">{{ $material->studyGuide->content['title'] ?? 'Study Guide' }}</div>
            <p class="text-sm text-muted">{{ $material->studyGuide->content['summary'] ?? '' }}</p>
            @foreach($material->studyGuide->content['sections'] ?? [] as $s)
                <div class="mt-3">
                    <div class="font-medium text-ink">{{ $s['heading'] }}</div>
                    <div class="text-sm text-muted whitespace-pre-line">{{ $s['content'] }}</div>
                </div>
            @endforeach
            @if(!empty($material->studyGuide->content['keyTerms']))
                <div class="mt-3">
                    <div class="font-medium text-ink">Key Terms</div>
                    <ul class="text-sm text-muted list-disc pl-5">
                        @foreach($material->studyGuide->content['keyTerms'] as $t)
                            <li><span class="font-medium">{{ $t['term'] }}</span> — {{ $t['definition'] }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif

    <div class="surface mt-5">
        <div class="px-5 py-3 border-b font-semibold text-ink">Flashcards ({{ $material->flashcards->count() }})</div>
        <ul class="divide-y text-sm">
            @forelse($material->flashcards as $f)
                <li class="px-5 py-3"><div class="font-medium">{{ $f->front }}</div><div class="text-muted">{{ $f->back }}</div></li>
            @empty
                <li class="px-5 py-4 text-faint">No flashcards yet.</li>
            @endforelse
        </ul>
    </div>

    <div class="surface mt-5">
        <div class="px-5 py-3 border-b font-semibold text-ink">Questions ({{ $material->questions->count() }})</div>
        <ul class="divide-y text-sm">
            @forelse($material->questions as $q)
                <li class="px-5 py-3"><div class="font-medium">{{ $q->question }}</div>
                    <div class="text-xs text-faint">Answer: {{ $q->options[(int)$q->answer] ?? $q->answer }}</div></li>
            @empty
                <li class="px-5 py-4 text-faint">No questions yet.</li>
            @endforelse
        </ul>
    </div>
</x-layouts.studyai>
