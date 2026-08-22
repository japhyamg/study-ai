<x-layouts.studyai title="{{ $material->title }} — Study">
    <div class="flex items-center justify-between mb-4">
        <div>
            <a href="{{ route('student.study.index') }}" class="text-xs text-accent">← Study sets</a>
            <h2 class="font-display text-xl mt-1 text-ink">{{ $material->title }}</h2>
            <p class="text-xs text-faint">{{ $material->subject?->name ?? 'General' }} · {{ $material->classRoom?->name ?? 'General' }}</p>
        </div>
    </div>

    @php
        $tab = request()->query('tab', 'flashcards');
        $tabs = [
            ['flashcards', 'Flashcards', $material->flashcards->count()],
            ['quiz', 'Quiz', $material->questions->count()],
            ['edit', 'Edit Questions', $material->questions->count()],
            ['images', 'Images', $material->images->count()],
            ['guide', 'Study Guide', $material->studyGuide ? 1 : 0],
        ];
    @endphp

    {{-- Tab navigation: buttons update URL via JS; links work server-side as fallback --}}
    <div class="border-b border-line mb-5 flex gap-1 flex-wrap">
        @foreach($tabs as [$key, $label, $count])
            @php($active = $tab === $key)
            @if($active)
                <button type="button" data-tab="{{ $key }}" class="tab-btn tab-btn-active">{{ $label }}@if($count > 0)<span class="text-xs text-faint">({{ $count }})</span>@endif</button>
            @else
                <button type="button" data-tab="{{ $key }}" class="tab-btn">{{ $label }}@if($count > 0)<span class="text-xs text-faint">({{ $count }})</span>@endif</button>
            @endif
        @endforeach
    </div>

    {{-- ===== Flashcards tab: 3D flip + SM-2 rating + keyboard shortcuts ===== --}}
    @php($due = $material->flashcards->filter(fn ($f) => is_null($f->due_date) || $f->due_date <= now()))
    @if($tab === 'flashcards')
    <div data-tab-panel="flashcards">
        @if($material->flashcards->isEmpty())
            <div class="empty">No flashcards for this material yet.<br>Upload a file with text content to generate them.</div>
        @elseif($due->isEmpty())
            <div class="empty">You've reviewed all due cards for this material. <a href="#!" onclick="location.reload()" class="text-accent">Refresh</a></div>
        @else
            @php($first = $due->first())
            <div class="flex items-center justify-between mb-3">
                <span class="text-sm text-muted">Card <span id="cur">1</span> of <span id="total">{{ $due->count() }}</span></span>
            </div>
            <div class="flashcard surface" id="card" style="min-height:18rem">
                <div class="flashcard-inner" id="inner">
                    <div class="flashcard-face flashcard-front" id="front">{{ $first->front }}</div>
                    <div class="flashcard-face flashcard-back" id="back">{{ $first->back }}</div>
                </div>
            </div>
            <div class="mt-4 flex gap-2" id="rating" style="display:none">
                <span class="text-xs text-muted mr-2">Rate difficulty (1–4):</span>
                <div class="inline-flex gap-1.5">
                    <form method="POST" action="{{ route('student.study.answer', $first) }}">@csrf
                        <button type="submit" name="quality" value="0" class="btn btn-danger btn-sm" title="Again">Again</button>
                    </form>
                    <form method="POST" action="{{ route('student.study.answer', $first) }}">@csrf
                        <button type="submit" name="quality" value="3" class="btn btn-ghost btn-sm" title="Hard">Hard</button>
                    </form>
                    <form method="POST" action="{{ route('student.study.answer', $first) }}">@csrf
                        <button type="submit" name="quality" value="4" class="btn btn-outline btn-sm" title="Good">Good</button>
                    </form>
                    <form method="POST" action="{{ route('student.study.answer', $first) }}">@csrf
                        <button type="submit" name="quality" value="5" class="btn btn-primary btn-sm" title="Easy">Easy</button>
                    </form>
                </div>
            </div>
            <button class="btn btn-ghost mt-3" id="flip-btn" onclick="flipCard()">Show answer</button>
        @endif
    </div>
    @endif

    {{-- ===== Quiz tab: read-only quiz with explanations ===== --}}
    @if($tab === 'quiz')
    <div data-tab-panel="quiz">
        @if($material->questions->isEmpty())
            <div class="empty">No quiz questions generated yet for this material.</div>
        @else
            <form id="quiz-form" class="space-y-4">
                @foreach($material->questions as $i => $q)
                    @php($opts = is_array($q->options) ? $q->options : [])
                    @php($correctIdx = $q->correct_idx ?? 0)
                    <div class="surface p-4">
                        <div class="font-medium">{{ $i + 1 }}. {{ $q->question }}</div>
                        @if(!empty($opts))
                            <div class="space-y-1 mt-2 text-sm">
                                @foreach($opts as $oi => $opt)
                                    <label class="flex items-center gap-2">
                                        <input type="radio" name="q{{ $q->id }}" value="{{ $oi }}" disabled {{ $correctIdx == $oi ? 'checked' : '' }}>
                                        {{ $opt }}
                                        @if($correctIdx == $oi)<span class="text-xs text-ok"> ✓ correct</span>@endif
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <div class="mt-2 text-sm text-muted">Answer: {{ $q->correct_idx }}</div>
                        @endif
                        @if($q->explanation)
                            <div class="mt-2 text-xs text-faint">{{ $q->explanation }}</div>
                        @endif
                    </div>
                @endforeach
            </form>
        @endif
    </div>
    @endif

    {{-- ===== Edit Questions tab ===== --}}
    @if($tab === 'edit')
    <div data-tab-panel="edit">
        @if($material->questions->isEmpty())
            <div class="empty mb-4">No questions yet. Generate questions by uploading content with more text.</div>
        @else
            <div class="space-y-4">
                @foreach($material->questions as $i => $q)
                    @php($opts = is_array($q->options) ? $q->options : [])
                    @php($correctIdx = $q->correct_idx ?? 0)
                    <div class="surface p-4">
                        <div class="font-medium mb-2">{{ $i + 1 }}. {{ $q->question }}</div>
                        @if(!empty($opts))
                            <div class="space-y-1 text-sm">
                                @foreach($opts as $oi => $opt)
                                    @php($correct = $correctIdx == $oi)
                                    <div class="flex items-center gap-2">
                                        <input type="radio" name="q{{ $q->id }}" value="{{ $oi }}" {{ $correct ? 'checked' : '' }} disabled>
                                        <span class="{{ $correct ? 'text-ok font-medium' : 'text-muted' }}">{{ $opt }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    @endif

    {{-- ===== Images tab ===== --}}
    @if($tab === 'images')
    <div data-tab-panel="images">
        @if($material->images->isEmpty())
            <div class="empty">No images generated for this material.</div>
        @else
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                @foreach($material->images as $img)
                    <img src="{{ $img->url ?? asset('storage/app/public/'.$img->path) }}" alt="p{{ $img->page_number }}" class="surface w-full h-24 object-cover border border-line">
                @endforeach
            </div>
        @endif
    </div>
    @endif

    {{-- ===== Study Guide tab ===== --}}
    @if($tab === 'guide')
    <div data-tab-panel="guide">
        @if(!$material->studyGuide)
            <div class="empty">No study guide generated yet.</div>
        @else
            @php($g = is_array($material->studyGuide->content) ? $material->studyGuide->content : ($material->studyGuide->content ? json_decode($material->studyGuide->content, true) : []))
            <div class="space-y-5">
                @if($g['title'] ?? null)
                    <h3 class="font-display text-lg text-ink">{{ $g['title'] }}</h3>
                @endif
                @if($g['summary'] ?? null)
                    <p class="text-sm text-muted leading-relaxed">{{ $g['summary'] }}</p>
                @endif
                @foreach(($g['sections'] ?? []) as $s)
                    <div class="surface p-4">
                        <div class="font-medium text-ink mb-1">{{ $s['heading'] ?? '' }}</div>
                        <div class="text-sm text-muted whitespace-pre-line">{{ $s['content'] ?? '' }}</div>
                    </div>
                @endforeach
                @if(empty($g['title']) && empty($g['summary']) && empty($g['sections']))
                    <p class="text-sm text-muted">{{ is_string($material->studyGuide->content) ? $material->studyGuide->content : '' }}</p>
                @endif
            </div>
        @endif
    </div>
    @endif
</x-layouts.studyai>

@push('scripts')
<script>
(function () {
  var panels = document.querySelectorAll('[data-tab-panel]');
  var btns   = document.querySelectorAll('[data-tab]');
  btns.forEach(function (b) {
    b.addEventListener('click', function (e) {
      var key = b.getAttribute('data-tab');
      panels.forEach(function (p) {
        p.style.display = (p.getAttribute('data-tab-panel') === key) ? '' : 'none';
      });
      btns.forEach(function (x) {
        x.classList.toggle('tab-btn-active', x.getAttribute('data-tab') === key);
      });
      var u = new URL(window.location.href);
      u.searchParams.set('tab', key);
      window.history.replaceState(null, '', u);
    });
  });
  // flashcard 3D flip + keyboard (1-4 ratings)
  window.flipCard = function () {
    var card = document.getElementById('card');
    var rating = document.getElementById('rating');
    var btn = document.getElementById('flip-btn');
    if (!card) return;
    card.classList.add('flipped');
    if (rating) rating.style.display = 'block';
    if (btn) btn.style.display = 'none';
  };
  document.addEventListener('keydown', function (e) {
    if (e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA')) return;
    if (document.getElementById('rating') && document.getElementById('rating').style.display === 'block') {
      var idx = { '1': 0, '2': 3, '3': 4, '4': 5 }[e.key];
      if (idx !== undefined) {
        var forms = document.getElementById('rating').querySelectorAll('button[name="quality"]');
        if (forms[idx]) forms[idx].form.requestSubmit();
      }
    }
  });
})();
</script>
@endpush