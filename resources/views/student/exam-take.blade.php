<x-layouts.studyai title="Take Exam: {{ $exam->title }}">
    <div class="flex items-center justify-between mb-4">
        <h2 class="font-display text-xl text-ink">{{ $exam->title }}</h2>
        @if($exam->duration)
            <div id="exam-timer" class="font-mono btn btn-ghost btn-sm" data-deadline="{{ $attempt->start_time->addMinutes($exam->duration)->timestamp }}">
                --:--
            </div>
        @endif
    </div>

    @php($questionList = $questions ?? $exam->questions()->orderBy('order')->get())
    @php($totalQ = $questionList->count())

    <form id="exam-form" method="POST" action="{{ route('student.exams.submit', [$exam, $attempt]) }}">
        @csrf
        <input type="hidden" name="auto_submitted" value="0">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            {{-- Questions + prev/next --}}
            <div class="lg:col-span-8 space-y-5">
                @foreach($questionList as $i => $q)
                    @php($opts = is_array($q->options) ? $q->options : [])
                    @php($qid = "q.{$q->id}")
                    <div class="question-block surface p-4" id="q{{ $i }}">
                        <div class="flex items-start justify-between mb-2">
                            <div class="font-medium text-ink">
                                {{ $i + 1 }}. {{ $q->question }}
                                <span class="text-xs text-faint"> · {{ $q->points ?? 1 }} pts</span>
                            </div>
                            @php($answered = request()->has($qid))
                            <span class="text-xs" style="color: {{ $answered ? 'var(--ok)' : 'var(--warn)' }};">
                                {{ $answered ? 'Answered' : 'Unanswered' }}
                            </span>
                        </div>
                        @if(!empty($opts))
                            <div class="space-y-1">
                                @foreach($opts as $idx => $opt)
                                    <label class="flex items-center gap-2 text-sm p-1 rounded hover:bg-paper-sunk transition-colors">
                                        <input type="radio" name="{{ $qid }}" value="{{ $idx }}" {{ request()->input($qid) == $idx ? 'checked' : '' }}>
                                        {{ $opt }}
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <input type="text" name="{{ $qid }}" class="input w-full" placeholder="Your answer" value="{{ request()->input($qid) }}">
                        @endif
                        @if($q->explanation)
                            <details class="mt-2 text-xs text-faint">
                                <summary class="cursor-pointer text-accent">Explanation</summary>
                                <div class="mt-1">{{ $q->explanation }}</div>
                            </details>
                        @endif
                    </div>
                @endforeach

                <div class="flex justify-between pt-2">
                    <button type="button" id="prev-btn" class="btn btn-ghost" disabled>← Previous</button>
                    <button type="button" id="next-btn" class="btn btn-ghost" disabled>Next →</button>
                </div>
            </div>

            {{-- Question navigator grid (sidebar) --}}
            <div class="lg:col-span-4">
                <div class="surface p-4" style="position:sticky; top:1.5rem">
                    <div class="font-medium text-sm text-ink mb-3">Question Navigator</div>
                    <div class="grid grid-cols-5 gap-2 mb-4">
                        @foreach($questionList as $i => $q)
                            @php($answered = request()->has("q.{$q->id}"))
                            <a href="#q{{ $i }}" class="nav-dot text-center text-xs py-2 rounded"
                               style="background: {{ $answered ? 'var(--ok)' : 'var(--line)' }}; color: {{ $answered ? '#fff' : 'var(--ink)' }};">
                                {{ $i + 1 }}
                            </a>
                        @endforeach
                    </div>
                    <div class="text-xs text-muted mb-4">
                        Answered: <span id="answered-count">{{ collect($questionList)->filter(fn ($q) => request()->has("q.{$q->id}"))->count() }}/<span id="total-count">{{ $totalQ }}</span></span>
                    </div>
                    <button type="button" id="submit-btn" class="btn btn-primary btn-block">Submit Exam</button>
                </div>
            </div>
        </div>
    </form>

    {{-- Submit confirmation modal — warns on unanswered --}}
    <div id="submit-modal" class="modal-backdrop">
        <div class="modal p-0">
            <div class="p-5">
                <h3 class="font-display text-lg text-ink mb-2">Submit Exam?</h3>
                <p class="text-sm text-muted mb-4">You have <span id="unanswered-count">0</span> unanswered questions.</p>
                <p class="text-xs text-faint mb-4">Submitted answers cannot be changed after submission.</p>
                <div class="flex gap-2 justify-end">
                    <button type="button" id="cancel-submit" class="btn btn-ghost">Cancel</button>
                    <button type="submit" form="exam-form" class="btn btn-primary">Submit</button>
                </div>
            </div>
        </div>
    </div>
</x-layouts.studyai>

@push('scripts')
<script>
(function () {
  // ── Timer: counts down, turns red + pulses under 5 min ──
  var el = document.getElementById('exam-timer');
  if (el) {
    var deadline = parseInt(el.dataset.deadline, 10) * 1000;
    var form = document.getElementById('exam-form');
    function pad(n) { return String(n).padStart(2, '0'); }
    function tick() {
      var left = Math.max(0, deadline - Date.now());
      var m = Math.floor(left / 60000), s = Math.floor((left % 60000) / 1000);
      el.textContent = pad(m) + ':' + pad(s);
      if (left <= 300000) { el.classList.add('timer-low'); }
      if (left <= 0) {
        el.textContent = 'Time up';
        if (form && !form.dataset.submitted) { form.dataset.submitted = '1'; form.submit(); }
        return;
      }
      setTimeout(tick, 1000);
    }
    tick();
  }

  // ── Answered count + submit modal ──
  function countAnswered() {
    var count = 0;
    document.querySelectorAll('input[type="radio"]').forEach(function (r) {
      if (r.checked) count++;
    });
    document.querySelectorAll('input[type="text"]').forEach(function (t) {
      if (t.value.trim()) count++;
    });
    return count;
  }
  function updateCounts() {
    var total = {{ $totalQ }};
    var answered = countAnswered();
    var uEl = document.getElementById('unanswered-count');
    var aEl = document.getElementById('answered-count');
    if (uEl) uEl.textContent = total - answered;
    if (aEl) aEl.textContent = answered;
  }
  document.getElementById('submit-btn')?.addEventListener('click', function () {
    updateCounts();
    document.getElementById('submit-modal').classList.add('open');
  });
  document.getElementById('cancel-submit')?.addEventListener('click', function () {
    document.getElementById('submit-modal').classList.remove('open');
  });

  // ── Prev/Next navigation between question blocks ──
  function updateNav() {
    var blocks = document.querySelectorAll('.question-block');
    var first = blocks[0], last = blocks[blocks.length - 1];
    document.getElementById('prev-btn').disabled = true; // all questions visible; prev/next scroll
  }
  document.getElementById('next-btn')?.addEventListener('click', function () {
    var blocks = document.querySelectorAll('.question-block');
    for (var i = 0; i < blocks.length; i++) {
      var r = blocks[i].getBoundingClientRect();
      if (r.top > 0) {
        if (blocks[i + 1]) blocks[i + 1].scrollIntoView({behavior:'smooth'});
        return;
      }
    }
  });
  document.getElementById('prev-btn')?.addEventListener('click', function () {
    var blocks = document.querySelectorAll('.question-block');
    for (var i = blocks.length - 1; i >= 0; i--) {
      var r = blocks[i].getBoundingClientRect();
      if (r.top > 0) {
        if (blocks[i - 1]) blocks[i - 1].scrollIntoView({behavior:'smooth'});
        return;
      }
    }
  });
})();
</script>
<style>
#exam-timer.timer-low { color: var(--danger); animation: pulse 1.5s ease-in-out infinite; }
@keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: .4; } }
.nav-dot { border: 1px solid var(--line); }
</style>
@endpush