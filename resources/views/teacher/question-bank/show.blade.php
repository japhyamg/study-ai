@php
    /**
     * One subject's bank.
     *
     * Questions are grouped by the study guide they came from, because that is
     * how a teacher thinks about them — "the quadratics questions", not "rows
     * 40 to 60". Editing happens in place: correcting a typo should not mean
     * losing your position in a list of two hundred.
     */
    $grouped = $questions->getCollection()->groupBy(fn ($q) => $q->topic ?: 'Added by hand');
    $filtered = ($filters['topic'] ?? null) || ($filters['q'] ?? null);

    $typeLabels = [
        'mcq' => 'Multiple choice',
        'true_false' => 'True / false',
        'fill_blank' => 'Fill in the blank',
        'short_answer' => 'Short answer',
        'essay' => 'Essay',
    ];
@endphp

<x-layouts.studyai title="Question bank"
                   :back-to="route('teacher.question-bank.index')" back-label="All subjects">
    <div x-data="{ adding: false }" @bank-form-cancel="adding = false">

        {{-- The subject and its action belong to the page, not the app chrome.
             Keeping the button here also means it can drive the panel directly
             instead of dispatching a window event into this scope. --}}
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <h2 class="font-display text-lg text-ink">{{ $subject->name }}</h2>

            <x-ui.button type="button" icon="plus" x-on:click="adding = ! adding">
                Add question
            </x-ui.button>
        </div>

        {{-- ── Add by hand ── --}}
        <div class="surface mb-4 p-5" x-show="adding" x-cloak>
            <x-bank-question-form :action="route('teacher.question-bank.store')"
                                  :subject="$subject" />
        </div>

        {{-- ── Filters ── --}}
        @if ($topics->isNotEmpty() || $filtered)
            <form method="GET" class="surface mt-3 flex flex-wrap items-end gap-3 p-4">
                @if ($topics->isNotEmpty())
                    <div class="min-w-[12rem] flex-1">
                        <label class="field-label" for="topic">Topic</label>
                        <select name="topic" id="topic" class="select" onchange="this.form.submit()">
                            <option value="">All topics</option>
                            @foreach ($topics as $topic)
                                <option value="{{ $topic }}" @selected(($filters['topic'] ?? null) === $topic)>
                                    {{ $topic }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="min-w-[12rem] flex-[2]">
                    <label class="field-label" for="q">Search</label>
                    <input name="q" id="q" class="input" value="{{ $filters['q'] ?? '' }}"
                           placeholder="Find a question…">
                </div>

                <button type="submit" class="btn btn-outline btn-sm">Filter</button>

                @if ($filtered)
                    <a href="{{ route('teacher.question-bank.show', $subject) }}"
                       class="text-xs text-accent hover:underline">Clear</a>
                @endif
            </form>
        @endif

        {{-- ── Questions ── --}}
        @if ($questions->isEmpty())
            <div class="mt-4">
                <x-ui.empty icon="database"
                            :title="$filtered ? 'Nothing matches those filters' : 'This bank is empty'"
                            :message="$filtered
                                ? 'Try a different topic or search term.'
                                : 'Questions arrive here once an admin approves a study guide for this subject.'" />
            </div>
        @else
            <p class="mt-4 text-xs text-faint">
                {{ number_format($questions->total()) }}
                {{ Str::plural('question', $questions->total()) }} in this bank.
            </p>

            <div class="mt-2 space-y-5">
                @foreach ($grouped as $topic => $rows)
                    <section>
                        <h2 class="mb-2 text-xs font-medium uppercase tracking-wider text-faint">
                            {{ $topic }}
                            <span class="tnum ms-1 normal-case text-muted">({{ $rows->count() }})</span>
                        </h2>

                        <div class="surface">
                            <ul class="divide-y">
                                @foreach ($rows as $question)
                                    <li class="px-5 py-3" x-data="{ editing: false }"
                                        @bank-form-cancel.stop="editing = false">
                                        {{-- Read --}}
                                        <div x-show="! editing">
                                            <div class="flex items-start justify-between gap-3">
                                                <p class="min-w-0 text-sm font-medium text-ink">{{ $question->question }}</p>

                                                <div class="flex shrink-0 gap-1">
                                                    <button type="button" class="btn-icon" title="Edit question"
                                                            @click="editing = true">
                                                        <x-icon name="pencil" />
                                                    </button>
                                                    <form method="POST"
                                                          action="{{ route('teacher.question-bank.destroy', $question) }}"
                                                          x-data
                                                          @submit.prevent="confirm('Remove this question from the bank?') && $el.submit()">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn-icon text-danger"
                                                                title="Remove from bank">
                                                            <x-icon name="trash" />
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>

                                            @if ($question->options)
                                                <ul class="mt-2 space-y-0.5">
                                                    @foreach ((array) $question->options as $i => $option)
                                                        @php $isCorrect = $option === $question->answer; @endphp
                                                        <li class="flex items-start gap-2 text-xs {{ $isCorrect ? 'font-medium text-success' : 'text-muted' }}">
                                                            <span class="w-4 shrink-0">{{ $isCorrect ? '✓' : chr(65 + $i) }}</span>
                                                            <span>{{ $option }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <p class="mt-1 text-xs text-muted">
                                                    Answer: <span class="text-ink">{{ $question->answer }}</span>
                                                </p>
                                            @endif

                                            <div class="mt-2 flex flex-wrap gap-1">
                                                <span class="badge">{{ $typeLabels[$question->type] ?? Str::headline($question->type) }}</span>
                                                <span class="badge">Difficulty {{ $question->difficulty }}/5</span>
                                            </div>
                                        </div>

                                        {{-- Edit --}}
                                        <div x-show="editing" x-cloak>
                                            <x-bank-question-form :action="route('teacher.question-bank.update', $question)"
                                                                  :question="$question"
                                                                  method="PUT" />
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </section>
                @endforeach
            </div>

            <div class="mt-4">{{ $questions->links() }}</div>
        @endif
    </div>
</x-layouts.studyai>
