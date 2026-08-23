@php
    $tab = request()->query('tab', 'guide');
    $guide = $material->studyGuide;
    $sections = $guide?->normalisedSections() ?? [];
    $keyTerms = $guide?->normalisedKeyTerms() ?? [];
    $topic = $material->topic;

    // Cards that are due now (or have never been seen).
    $due = $material->flashcards
        ->filter(fn ($card) => is_null($card->due_date) || $card->due_date <= now())
        ->values();

    $tabs = [
        ['guide', 'Study guide', count($sections)],
        ['flashcards', 'Flashcards', $due->count()],
        ['quiz', 'Quiz', $material->questions->count()],
    ];
@endphp

<x-layouts.studyai :title="$material->title"
                   :subtitle="collect([$material->subject?->name, $material->classArm?->fullName()])->filter()->join(' · ')">

    <a href="{{ route('student.study.index') }}" class="text-xs text-accent">← All study sets</a>

    <div class="mb-5 mt-3 flex flex-wrap gap-1 border-b border-line">
        @foreach ($tabs as [$key, $label, $count])
            <button type="button" data-tab="{{ $key }}"
                    class="tab-btn {{ $tab === $key ? 'active' : '' }}">
                {{ $label }}@if ($count > 0)<span class="ml-1 text-xs text-faint">{{ $count }}</span>@endif
            </button>
        @endforeach
    </div>

    {{-- ─────────────── Study guide ─────────────── --}}
    <div data-tab-panel="guide" @style(['display: none' => $tab !== 'guide'])>
        @if (! $guide || (! $sections && ! $guide->summary))
            <x-ui.empty icon="book" title="No study guide"
                        message="Your teacher hasn't generated a study guide for this material." />
        @else
            <div class="grid gap-5 lg:grid-cols-3">
                <div class="space-y-4 lg:col-span-2">
                    @if ($guide->summary)
                        <p class="text-sm leading-relaxed text-muted">{{ $guide->summary }}</p>
                    @endif

                    @foreach ($sections as $section)
                        <section class="surface p-5">
                            <h3 class="font-medium text-ink">{{ $section['heading'] }}</h3>
                            <div class="mt-2 whitespace-pre-line text-sm leading-relaxed text-muted">{{ $section['body'] }}</div>
                        </section>
                    @endforeach
                </div>

                <div class="space-y-4">
                    @if ($keyTerms)
                        <x-ui.card title="Key terms">
                            <dl class="space-y-2.5">
                                @foreach ($keyTerms as $term)
                                    <div class="text-sm">
                                        <dt class="font-medium text-ink">{{ $term['term'] }}</dt>
                                        <dd class="text-muted">{{ $term['definition'] }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </x-ui.card>
                    @endif

                    @if ($topic)
                        @php
                            $prerequisites = $topic->prerequisites();
                            $followUps = $topic->followUps();
                            $unlocks = $topic->unlocks();
                        @endphp

                        @if ($prerequisites->isNotEmpty())
                            <x-ui.card title="Study these first"
                                       subtitle="This topic builds on them.">
                                <ul class="space-y-1.5 text-sm">
                                    @foreach ($prerequisites as $prerequisite)
                                        <li class="text-muted">{{ $prerequisite->name }}</li>
                                    @endforeach
                                </ul>
                            </x-ui.card>
                        @endif

                        @if ($followUps->isNotEmpty() || $unlocks->isNotEmpty())
                            <x-ui.card title="What comes next">
                                <ul class="space-y-1.5 text-sm">
                                    @foreach ($followUps->merge($unlocks)->unique('id') as $next)
                                        <li class="text-muted">{{ $next->name }}</li>
                                    @endforeach
                                </ul>
                            </x-ui.card>
                        @endif
                    @endif
                </div>
            </div>
        @endif
    </div>

    {{-- ─────────────── Flashcards ─────────────── --}}
    <div data-tab-panel="flashcards" @style(['display: none' => $tab !== 'flashcards'])>
        @if ($material->flashcards->isEmpty())
            <x-ui.empty icon="layers" title="No flashcards"
                        message="There are no flashcards for this material yet." />
        @elseif ($due->isEmpty())
            <x-ui.empty icon="check-circle" title="All caught up"
                        message="You've reviewed every card that's due. Come back when the next one is scheduled." />
        @else
            <div class="mx-auto max-w-2xl" x-data="flashcardDeck()">
                <div class="mb-3 flex items-center justify-between text-sm text-muted">
                    <span>Card <span class="tnum" x-text="index + 1"></span> of <span class="tnum">{{ $due->count() }}</span></span>
                    <span class="text-xs text-faint">Space to flip · 1–4 to rate</span>
                </div>

                <div class="flashcard surface" :class="{ 'flipped': flipped }" @click="flip()">
                    <div class="flashcard-inner">
                        <div class="flashcard-face flashcard-front" x-text="card.front"></div>
                        <div class="flashcard-face flashcard-back" x-text="card.back"></div>
                    </div>
                </div>

                <div class="mt-4" x-show="flipped" x-cloak>
                    <div class="mb-2 text-xs text-muted">How well did you know it?</div>
                    <div class="grid grid-cols-4 gap-2">
                        @foreach ([['0', 'Again', 'btn-danger'], ['3', 'Hard', 'btn-outline'], ['4', 'Good', 'btn-outline'], ['5', 'Easy', 'btn-primary']] as [$quality, $label, $class])
                            <form method="POST" :action="answerUrl" class="contents">
                                @csrf
                                <input type="hidden" name="quality" value="{{ $quality }}">
                                <button type="submit" class="btn {{ $class }} btn-sm w-full">{{ $label }}</button>
                            </form>
                        @endforeach
                    </div>
                </div>

                <button type="button" class="btn btn-ghost mt-3" x-show="!flipped" @click="flip()">
                    Show answer
                </button>
            </div>
        @endif
    </div>

    {{-- ─────────────── Quiz ─────────────── --}}
    <div data-tab-panel="quiz" @style(['display: none' => $tab !== 'quiz'])>
        @if ($material->questions->isEmpty())
            <x-ui.empty icon="clipboard" title="No quiz"
                        message="There are no quiz questions for this material yet." />
        @else
            <div class="mx-auto max-w-3xl" x-data="quiz()">
                <ol class="space-y-4">
                    @foreach ($material->questions as $i => $question)
                        @php $options = (array) $question->options; @endphp
                        <li class="surface p-4" x-data="{ id: '{{ $question->id }}', correct: {{ (int) $question->correct_idx }} }">
                            <div class="text-sm font-medium text-ink">{{ $i + 1 }}. {{ $question->question }}</div>

                            <div class="mt-2.5 space-y-1.5">
                                @foreach ($options as $index => $option)
                                    <label class="flex cursor-pointer items-start gap-2 rounded-md px-2 py-1.5 text-sm transition-colors"
                                           :class="answerClass(id, {{ $index }}, correct)">
                                        <input type="radio" name="q-{{ $question->id }}" value="{{ $index }}"
                                               class="mt-0.5" :disabled="submitted"
                                               @change="answers[id] = {{ $index }}">
                                        <span>{{ $option }}</span>
                                    </label>
                                @endforeach
                            </div>

                            @if ($question->explanation)
                                <p class="mt-2 text-xs text-faint" x-show="submitted" x-cloak>
                                    {{ $question->explanation }}
                                </p>
                            @endif
                        </li>
                    @endforeach
                </ol>

                <div class="mt-5 flex items-center gap-3">
                    <button type="button" class="btn btn-primary" x-show="!submitted" @click="submit()">
                        Check answers
                    </button>

                    <div x-show="submitted" x-cloak class="flex items-center gap-3">
                        <span class="text-sm text-ink">
                            <span class="tnum font-medium" x-text="score"></span> of {{ $material->questions->count() }} correct
                        </span>
                        <button type="button" class="btn btn-ghost btn-sm" @click="reset()">Try again</button>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @push('scripts')
        <script>
            // ── Tabs ──
            (function () {
                const panels = document.querySelectorAll('[data-tab-panel]');
                const buttons = document.querySelectorAll('[data-tab]');

                buttons.forEach((button) => {
                    button.addEventListener('click', () => {
                        const key = button.dataset.tab;

                        panels.forEach((panel) => {
                            panel.style.display = panel.dataset.tabPanel === key ? '' : 'none';
                        });

                        buttons.forEach((other) => {
                            other.classList.toggle('active', other.dataset.tab === key);
                        });

                        const url = new URL(window.location.href);
                        url.searchParams.set('tab', key);
                        window.history.replaceState(null, '', url);
                    });
                });
            })();

            // ── Flashcards ──
            function flashcardDeck() {
                return {
                    cards: @json($due->map(fn ($card) => ['id' => $card->id, 'front' => $card->front, 'back' => $card->back])->values()),
                    index: 0,
                    flipped: false,

                    get card() {
                        return this.cards[this.index] ?? { front: '', back: '' };
                    },

                    get answerUrl() {
                        // Built from a named route with a placeholder id so the
                        // prefix stays correct if routing changes.
                        return '{{ route('student.study.answer', ['flashcard' => '__ID__']) }}'.replace('__ID__', this.card.id);
                    },

                    flip() {
                        this.flipped = !this.flipped;
                    },

                    /**
                     * Advance to another card.
                     *
                     * The card must be un-flipped *before* its text changes,
                     * otherwise the answer of the next card is visible for the
                     * length of the flip animation. Everything that changes
                     * `index` goes through here for exactly that reason.
                     */
                    go(next) {
                        if (next < 0 || next >= this.cards.length) return;

                        if (!this.flipped) {
                            this.index = next;
                            return;
                        }

                        this.flipped = false;
                        setTimeout(() => { this.index = next; }, 350);
                    },

                    init() {
                        document.addEventListener('keydown', (event) => {
                            const tag = event.target.tagName;
                            if (tag === 'INPUT' || tag === 'TEXTAREA') return;

                            if (event.code === 'Space') {
                                event.preventDefault();
                                this.flip();
                                return;
                            }

                            if (!this.flipped) return;

                            const quality = { '1': 0, '2': 3, '3': 4, '4': 5 }[event.key];
                            if (quality === undefined) return;

                            const forms = this.$el.querySelectorAll('form');
                            const map = { 0: 0, 3: 1, 4: 2, 5: 3 };
                            forms[map[quality]]?.requestSubmit();
                        });
                    },
                };
            }

            // ── Quiz ──
            function quiz() {
                return {
                    answers: {},
                    submitted: false,
                    score: 0,

                    submit() {
                        this.submitted = true;
                        this.score = 0;

                        this.$el.querySelectorAll('[x-data]').forEach((node) => {
                            const data = Alpine.$data(node);
                            if (data.id !== undefined && this.answers[data.id] === data.correct) {
                                this.score++;
                            }
                        });
                    },

                    reset() {
                        this.submitted = false;
                        this.answers = {};
                        this.score = 0;
                        this.$el.querySelectorAll('input[type=radio]').forEach((input) => {
                            input.checked = false;
                        });
                    },

                    answerClass(id, index, correct) {
                        if (!this.submitted) return '';
                        if (index === correct) return 'bg-success/10 text-success';
                        if (this.answers[id] === index) return 'bg-danger/10 text-danger';
                        return '';
                    },
                };
            }
        </script>
    @endpush
</x-layouts.studyai>
