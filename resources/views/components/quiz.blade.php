@props(['questions'])

@php
    /**
     * Practice quiz.
     *
     * One question at a time with immediate feedback, rather than a long form
     * marked at the end: the explanation is worth most while the question is
     * still in mind, and a wall of radio buttons discourages starting at all.
     *
     * Short-answer questions cannot be auto-marked, so they are self-assessed
     * against a model answer. Rendering them as multiple choice — which the
     * previous version did — showed a single radio button labelled with the
     * answer.
     */
    $items = collect($questions)->values()->map(fn ($q, $i) => [
        'n' => $i + 1,
        'id' => $q->id,
        'question' => (string) $q->question,
        'options' => array_values((array) $q->options),
        'correct' => (int) $q->correct_idx,
        'explanation' => (string) ($q->explanation ?? ''),
        'difficulty' => (int) ($q->difficulty ?: 1),
        'open' => ($q->type ?? 'multiple-choice') === 'short-answer',
    ]);
@endphp

<div class="mx-auto max-w-3xl" x-data="quizRunner({ questions: @js($items) })">

    {{-- ── Progress ── --}}
    <div class="mb-3 flex items-center justify-between gap-3 text-sm" x-show="! finished" x-cloak>
        <span class="text-muted">
            Question <span class="tnum" x-text="index + 1"></span> of <span class="tnum">{{ $items->count() }}</span>
            <span class="text-faint">· <span class="tnum" x-text="score"></span> correct</span>
        </span>
        <div class="hidden h-1.5 w-40 overflow-hidden rounded-full bg-surface-sunk sm:block">
            <div class="h-full rounded-full bg-accent transition-all duration-300" :style="`width: ${percent()}%`"></div>
        </div>
    </div>

    {{-- ── Question ── --}}
    <div class="surface p-5" x-show="! finished" x-cloak>
        <div class="flex items-center gap-2">
            <span class="badge" x-text="'Difficulty ' + q.difficulty + '/5'"></span>
            <span class="badge" x-show="q.open" x-cloak>Written answer</span>
        </div>

        <p class="mt-3 whitespace-pre-line text-base font-medium text-ink" x-text="q.question"></p>

        {{-- Multiple choice --}}
        <div class="mt-4 space-y-2" x-show="! q.open">
            <template x-for="(option, i) in q.options" :key="i">
                <button type="button"
                        class="flex w-full items-start gap-3 rounded-lg border-2 p-3 text-start text-sm transition-colors"
                        :class="optionClass(i)"
                        :disabled="answered"
                        @click="choose(i)">
                    <span class="tnum shrink-0 font-medium" x-text="String.fromCharCode(65 + i) + '.'"></span>
                    <span class="min-w-0 whitespace-pre-line" x-text="option"></span>
                </button>
            </template>
        </div>

        {{-- Short answer: self-assessed against the model answer --}}
        <div class="mt-4 space-y-3" x-show="q.open" x-cloak>
            <textarea class="textarea" rows="4" placeholder="Type your answer…"
                      x-model="written" :disabled="answered"></textarea>

            <button type="button" class="btn btn-primary btn-sm" x-show="! answered" @click="revealModel()">
                Show model answer
            </button>

            <div x-show="answered" x-cloak class="rounded-lg border border-success/30 bg-success/5 p-3">
                <p class="text-xs font-medium text-success">Model answer</p>
                <p class="mt-1 whitespace-pre-line text-sm text-ink" x-text="q.options[0] ?? ''"></p>

                <p class="mt-3 text-xs text-muted">Did you get it right?</p>
                <div class="mt-2 flex gap-2">
                    <button type="button" class="btn btn-outline btn-sm" @click="selfMark(true)"
                            :class="selfCorrect === true && 'border-success/50 text-success'">Yes</button>
                    <button type="button" class="btn btn-outline btn-sm" @click="selfMark(false)"
                            :class="selfCorrect === false && 'border-danger/50 text-danger'">Not quite</button>
                </div>
            </div>
        </div>

        {{-- Explanation --}}
        <div class="mt-4 rounded-lg bg-surface-sunk p-3" x-show="answered && q.explanation" x-cloak
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            <p class="text-xs font-medium text-ink">Explanation</p>
            <p class="mt-1 whitespace-pre-line text-sm text-muted" x-text="q.explanation"></p>
        </div>

        <div class="mt-4 flex justify-end" x-show="answered" x-cloak>
            <button type="button" class="btn btn-primary btn-sm" @click="next()">
                <span x-text="index < questions.length - 1 ? 'Next question' : 'Finish'"></span>
                <x-icon name="chevron-right" />
            </button>
        </div>
    </div>

    {{-- ── Results ── --}}
    <div x-show="finished" x-cloak>
        <div class="surface p-6 text-center">
            <p class="tnum text-4xl font-semibold" :class="scoreTone()" x-text="percentScore() + '%'"></p>
            <p class="mt-1 font-medium text-ink">Quiz complete</p>
            <p class="mt-0.5 text-sm text-muted">
                <span class="tnum" x-text="score"></span> of <span class="tnum">{{ $items->count() }}</span> correct
            </p>
            <button type="button" class="btn btn-outline btn-sm mt-4" @click="restart()">Try again</button>
        </div>

        {{-- Per-question review, so a wrong answer is a learning moment. --}}
        <ol class="mt-4 space-y-2">
            <template x-for="(entry, i) in results" :key="i">
                <li class="rounded-lg border p-3 text-sm"
                    :class="entry.correct ? 'border-success/30 bg-success/5' : 'border-danger/30 bg-danger/5'">
                    <div class="flex items-start gap-2">
                        <span class="shrink-0 font-medium"
                              :class="entry.correct ? 'text-success' : 'text-danger'"
                              x-text="entry.correct ? '✓' : '✗'"></span>
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-ink" x-text="entry.question"></p>
                            <p class="mt-1 text-xs text-muted" x-show="! entry.correct && ! entry.open" x-cloak>
                                Your answer: <span x-text="entry.chosen"></span> ·
                                Correct: <span class="text-success" x-text="entry.answer"></span>
                            </p>
                        </div>
                    </div>
                </li>
            </template>
        </ol>
    </div>
</div>

@once
    @push('scripts')
        <script>
            function quizRunner({ questions }) {
                return {
                    questions,
                    index: 0,
                    selected: null,
                    written: '',
                    selfCorrect: null,
                    answered: false,
                    score: 0,
                    results: [],
                    finished: false,

                    get q() {
                        return this.questions[this.index] ?? {
                            question: '', options: [], correct: 0, explanation: '', difficulty: 1, open: false,
                        };
                    },

                    percent() {
                        return this.questions.length === 0
                            ? 0
                            : Math.round((this.index / this.questions.length) * 100);
                    },
                    percentScore() {
                        return this.questions.length === 0
                            ? 0
                            : Math.round((this.score / this.questions.length) * 100);
                    },
                    scoreTone() {
                        const p = this.percentScore();
                        return p >= 70 ? 'text-success' : p >= 40 ? 'text-warning' : 'text-danger';
                    },

                    choose(i) {
                        if (this.answered) return;

                        this.selected = i;
                        this.answered = true;

                        const correct = i === this.q.correct;
                        if (correct) this.score++;

                        this.results.push({
                            question: this.q.question,
                            correct,
                            open: false,
                            chosen: this.q.options[i] ?? '',
                            answer: this.q.options[this.q.correct] ?? '',
                        });
                    },

                    // A written answer cannot be auto-marked, so the reader
                    // marks it against the model answer.
                    revealModel() { this.answered = true; },

                    selfMark(correct) {
                        if (this.selfCorrect === correct) return;

                        // Allow changing the verdict before moving on.
                        if (this.selfCorrect === true) this.score--;
                        this.selfCorrect = correct;
                        if (correct) this.score++;
                    },

                    next() {
                        if (this.q.open) {
                            this.results.push({
                                question: this.q.question,
                                correct: this.selfCorrect === true,
                                open: true,
                                chosen: this.written,
                                answer: this.q.options[0] ?? '',
                            });
                        }

                        if (this.index < this.questions.length - 1) {
                            this.index++;
                            this.selected = null;
                            this.written = '';
                            this.selfCorrect = null;
                            this.answered = false;
                            return;
                        }

                        this.finished = true;
                    },

                    optionClass(i) {
                        if (! this.answered) {
                            return this.selected === i
                                ? 'border-accent bg-brand-50'
                                : 'border-line hover:border-accent/50';
                        }
                        if (i === this.q.correct) return 'border-success bg-success/10 text-ink';
                        if (i === this.selected) return 'border-danger bg-danger/10 text-ink';
                        return 'border-line opacity-50';
                    },

                    restart() {
                        this.index = 0;
                        this.selected = null;
                        this.written = '';
                        this.selfCorrect = null;
                        this.answered = false;
                        this.score = 0;
                        this.results = [];
                        this.finished = false;
                    },
                };
            }
        </script>
    @endpush
@endonce
