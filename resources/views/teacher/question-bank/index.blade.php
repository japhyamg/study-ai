@php
    /**
     * The subject question bank.
     *
     * Fills up on approval: every quiz question an admin signs off joins its
     * subject's pool, tagged with the study guide it came from. A teacher only
     * sees subjects they are assigned to, so the bank reads as "my subject's
     * accumulated work" rather than a school-wide dump.
     */
    $hasSubjects = $subjects->isNotEmpty();
    $filtered = ($filters['subject'] ?? null) || ($filters['topic'] ?? null) || ($filters['q'] ?? null);
@endphp

<x-layouts.studyai title="Question bank"
                   subtitle="Approved questions for the subjects you teach.">

    <x-slot:actions>
        <button type="button" class="btn btn-outline btn-sm" @click="$dispatch('bank-add')">
            <x-icon name="plus" /> Add question
        </button>
    </x-slot:actions>

    <div x-data="{ adding: false }" @bank-add.window="adding = true">

        @unless ($hasSubjects)
            <div class="alert alert-info mb-4" role="status">
                <x-icon name="info" class="mt-px flex-none" />
                <span>
                    You are not assigned to any subject yet, so there is no bank to show.
                    An administrator assigns subjects under Academics.
                </span>
            </div>
        @endunless

        {{-- ── Filters ── --}}
        @if ($hasSubjects)
            <form method="GET" class="surface mb-4 flex flex-wrap items-end gap-3 p-4">
                <div class="min-w-[10rem] flex-1">
                    <label class="field-label" for="subject">Subject</label>
                    <select name="subject" id="subject" class="select" onchange="this.form.submit()">
                        <option value="">All my subjects</option>
                        @foreach ($subjects as $subject)
                            <option value="{{ $subject->id }}" @selected(($filters['subject'] ?? null) === $subject->id)>
                                {{ $subject->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                @if ($topics->isNotEmpty())
                    <div class="min-w-[10rem] flex-1">
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
                    <a href="{{ route('teacher.question-bank.index') }}" class="text-xs text-accent hover:underline">
                        Clear
                    </a>
                @endif
            </form>
        @endif

        {{-- ── Add by hand ── --}}
        <form method="POST" action="{{ route('teacher.question-bank.store') }}"
              class="surface mb-4 space-y-3 p-5" x-show="adding" x-cloak>
            @csrf
            <p class="text-sm font-medium text-ink">New question</p>

            <div class="grid gap-3 sm:grid-cols-2">
                <x-ui.field label="Subject" name="subject_id">
                    <select name="subject_id" class="select">
                        @foreach ($subjects as $subject)
                            <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field label="Type" name="type">
                    <select name="type" class="select">
                        <option value="mcq">Multiple choice</option>
                        <option value="true_false">True / false</option>
                        <option value="fill_blank">Fill in the blank</option>
                        <option value="short_answer">Short answer</option>
                        <option value="essay">Essay</option>
                    </select>
                </x-ui.field>
            </div>

            <x-ui.field label="Question" name="question" required>
                <textarea name="question" rows="2" class="textarea" required></textarea>
            </x-ui.field>

            <div class="grid gap-3 sm:grid-cols-2">
                <x-ui.field label="Answer" name="answer" required>
                    <input name="answer" class="input" required>
                </x-ui.field>

                <x-ui.field label="Difficulty" name="difficulty" hint="1 easiest, 5 hardest">
                    <select name="difficulty" class="select">
                        @for ($d = 1; $d <= 5; $d++)
                            <option value="{{ $d }}">{{ $d }}</option>
                        @endfor
                    </select>
                </x-ui.field>
            </div>

            <x-ui.field label="Explanation" name="explanation">
                <textarea name="explanation" rows="2" class="textarea"></textarea>
            </x-ui.field>

            <div class="flex justify-end gap-2 border-t border-line pt-3">
                <button type="button" class="btn btn-ghost btn-sm" @click="adding = false">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Add question</button>
            </div>
        </form>

        {{-- ── The bank ── --}}
        @if ($questions->isEmpty())
            <x-ui.empty icon="database"
                        :title="$filtered ? 'Nothing matches those filters' : 'The bank is empty'"
                        :message="$filtered
                            ? 'Try a different subject or search term.'
                            : 'Questions join the bank automatically once an admin approves the study guide they came from.'" />
        @else
            <div class="mb-2 flex items-baseline justify-between">
                <span class="text-xs text-faint">
                    {{ number_format($questions->total()) }}
                    {{ Str::plural('question', $questions->total()) }}
                </span>
            </div>

            <div class="surface">
                <ul class="divide-y">
                    @foreach ($questions as $question)
                        <li class="flex items-start justify-between gap-3 px-5 py-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-ink">{{ $question->question }}</p>
                                <p class="mt-0.5 text-xs text-muted">
                                    Answer: <span class="text-ink">{{ $question->answer }}</span>
                                </p>
                                <div class="mt-1.5 flex flex-wrap gap-1">
                                    @if ($question->subject)
                                        <span class="badge">{{ $question->subject->name }}</span>
                                    @endif
                                    @if ($question->topic)
                                        {{-- Kept even after the study guide is deleted, so the
                                             question does not lose its context. --}}
                                        <span class="badge">{{ $question->topic }}</span>
                                    @endif
                                    <span class="badge">{{ Str::headline($question->type) }}</span>
                                    <span class="badge">Difficulty {{ $question->difficulty }}/5</span>
                                </div>
                            </div>

                            <form method="POST" action="{{ route('teacher.question-bank.destroy', $question) }}"
                                  x-data
                                  @submit.prevent="confirm('Remove this question from the bank?') && $el.submit()">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-icon text-danger" title="Remove from bank">
                                    <x-icon name="trash" />
                                </button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="mt-4">{{ $questions->links() }}</div>
        @endif
    </div>
</x-layouts.studyai>
