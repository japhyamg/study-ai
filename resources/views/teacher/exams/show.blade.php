@php
    $typeLabels = [
        'mcq' => 'Multiple choice',
        'true_false' => 'True / false',
        'fill_blank' => 'Fill in the blank',
        'short_answer' => 'Short answer',
        'essay' => 'Essay',
    ];

    $questions = $exam->questions->sortBy('order')->values();
    $totalPoints = $questions->sum(fn ($q) => $q->points ?? 1);
    $published = $exam->status === 'published';
@endphp

<x-layouts.studyai :title="$exam->title" subtitle="Questions and setup">
    <x-slot:actions>
        <x-ui.button href="{{ route('teacher.exams.edit', $exam) }}" variant="ghost" size="sm">Edit settings</x-ui.button>

        @if($published)
            <form method="POST" action="{{ route('teacher.exams.unpublish', $exam) }}" class="inline">
                @csrf @method('PUT')
                <x-ui.button type="submit" variant="ghost" size="sm">Unpublish</x-ui.button>
            </form>
        @else
            <form method="POST" action="{{ route('teacher.exams.publish', $exam) }}" class="inline">
                @csrf @method('PUT')
                <x-ui.button type="submit" size="sm" @disabled($questions->isEmpty())>Publish</x-ui.button>
            </form>
        @endif
    </x-slot:actions>

    @if (session('status'))
        <div class="alert-info mb-4">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert-danger mb-4">
            <ul class="list-disc ps-4">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- ── Setup at a glance ── --}}
    <div class="surface mb-6 flex flex-wrap items-center gap-x-6 gap-y-2 px-5 py-3 text-sm">
        <span class="{{ $published ? 'badge badge-success' : 'badge' }}">{{ $published ? 'Published' : 'Draft' }}</span>

        <span class="text-muted">
            <span class="tnum text-ink">{{ $questions->count() }}</span>
            {{ Str::plural('question', $questions->count()) }}
            · <span class="tnum text-ink">{{ $totalPoints }}</span> {{ Str::plural('point', $totalPoints) }}
        </span>

        <span class="text-muted">{{ $exam->duration ? $exam->duration.' min' : 'Untimed' }}</span>
        <span class="text-muted">Pass <span class="tnum text-ink">{{ (int) $exam->pass_mark }}</span>%</span>

        @if($exam->shuffle_questions || $exam->shuffle_options)
            <span class="text-muted">
                Shuffles
                {{ collect(['questions' => $exam->shuffle_questions, 'options' => $exam->shuffle_options])
                    ->filter()->keys()->join(' and ') }}
            </span>
        @endif

        @if($exam->start_time || $exam->end_time)
            <span class="text-muted">
                {{ $exam->start_time?->format('j M, g:ia') ?? 'Open now' }}
                → {{ $exam->end_time?->format('j M, g:ia') ?? 'no close' }}
            </span>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- ── The paper ── --}}
        <div class="lg:col-span-2" x-data="{ editing: null }">
            @if ($questions->isEmpty())
                <x-ui.empty icon="clipboard" title="No questions yet"
                            message="Add one from the subject's bank, or write your own." />
            @else
                <ol class="space-y-3">
                    @foreach ($questions as $q)
                        @php
                            $opts = is_array($q->options) ? $q->options : [];
                            $fromBank = (bool) $q->bank_id;
                        @endphp

                        <li class="surface px-5 py-4" @bank-form-cancel.stop="editing = null">
                            <div x-show="editing !== '{{ $q->id }}'">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="text-sm text-ink">
                                        <span class="tnum text-muted">{{ $loop->iteration }}.</span>
                                        {{ $q->question }}
                                    </p>

                                    <div class="flex shrink-0 items-center gap-1">
                                        <button type="button" class="btn-icon text-muted hover:text-ink"
                                                @click="editing = '{{ $q->id }}'" title="Edit question">
                                            <x-icon name="pencil" />
                                        </button>

                                        <form method="POST" action="{{ route('teacher.exams.questions.destroy', [$exam, $q]) }}"
                                              onsubmit="return confirm('Remove this question from the exam?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn-icon text-muted hover:text-danger" title="Remove question">
                                                <x-icon name="trash" />
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                @if ($opts)
                                    <ul class="mt-2 space-y-1">
                                        @foreach ($opts as $opt)
                                            @php $isAnswer = (string) $opt === (string) $q->answer; @endphp
                                            <li class="flex items-start gap-2 text-xs {{ $isAnswer ? 'text-ink' : 'text-muted' }}">
                                                <span class="shrink-0 {{ $isAnswer ? 'text-success' : 'text-faint' }}">
                                                    {{ $isAnswer ? '✓' : '·' }}
                                                </span>
                                                <span>{{ $opt }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p class="mt-2 text-xs text-muted">
                                        <span class="text-faint">Answer:</span> {{ $q->answer }}
                                    </p>
                                @endif

                                <div class="mt-2.5 flex flex-wrap items-center gap-2 text-xs text-faint">
                                    <span>{{ $typeLabels[$q->type] ?? $q->type }}</span>
                                    <span>·</span>
                                    <span><span class="tnum">{{ $q->points ?? 1 }}</span> {{ Str::plural('pt', $q->points ?? 1) }}</span>
                                    @if ($fromBank)
                                        <span>·</span>
                                        <span>From bank</span>
                                    @endif
                                </div>
                            </div>

                            <div x-show="editing === '{{ $q->id }}'" x-cloak>
                                <x-bank-question-form
                                    :action="route('teacher.exams.questions.update', [$exam, $q])"
                                    :question="$q"
                                    method="PUT"
                                    show-points />
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>

        {{-- ── Adding questions ── --}}
        <div class="space-y-5">
            {{-- From the subject's bank: everything approved for this subject
                 over time, grouped by the study guide it came from. Questions
                 already on the exam are filtered out server-side. --}}
            <div class="surface p-5">
                <div class="mb-1 font-semibold text-ink">Add from question bank</div>

                @if ($questionBank->isEmpty())
                    <p class="text-xs text-faint">
                        @if (! $exam->subject_id)
                            Set a subject in the exam settings to pull from its bank.
                        @else
                            Nothing available yet. Questions join the bank for a subject once an
                            admin approves the study guide they came from.
                        @endif
                    </p>
                @else
                    <p class="mb-3 text-xs text-faint">
                        {{ $questionBank->flatten()->count() }} approved
                        {{ Str::plural('question', $questionBank->flatten()->count()) }} for this subject.
                    </p>

                    <form method="POST" action="{{ route('teacher.exams.questions.from-bank', $exam) }}"
                          x-data="{ picked: 0 }">
                        @csrf
                        <div class="max-h-80 space-y-3 overflow-y-auto pe-1">
                            @foreach ($questionBank as $topic => $rows)
                                <div>
                                    <p class="mb-1 text-xs font-medium text-muted">{{ $topic }}</p>
                                    <div class="space-y-1">
                                        @foreach ($rows as $row)
                                            <label class="flex cursor-pointer items-start gap-2 rounded-md p-1.5 text-xs transition-colors hover:bg-surface-sunk">
                                                <input type="checkbox" name="bank_ids[]" value="{{ $row->id }}"
                                                       class="checkbox mt-0.5 shrink-0"
                                                       @change="picked += $event.target.checked ? 1 : -1">
                                                <span class="min-w-0 text-ink">{{ Str::limit($row->question, 110) }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <button type="submit" class="btn btn-primary btn-sm mt-3 w-full" :disabled="picked === 0">
                            Add <span x-show="picked > 0" x-cloak><span x-text="picked"></span></span> selected
                        </button>
                    </form>
                @endif
            </div>

            {{-- Write one by hand. Same form as the bank, so the fields follow
                 the type instead of showing options for an essay. --}}
            <div class="surface p-5" x-data="{ adding: false }" @bank-form-cancel="adding = false">
                <div class="flex items-center justify-between">
                    <div class="font-semibold text-ink">Write a question</div>
                    <button type="button" class="text-xs text-accent hover:underline"
                            x-show="! adding" @click="adding = true">
                        + New
                    </button>
                </div>

                <p class="mt-1 text-xs text-faint" x-show="! adding">
                    Added to this exam only. It does not join the subject bank.
                </p>

                <div x-show="adding" x-cloak class="mt-3">
                    <x-bank-question-form
                        :action="route('teacher.exams.questions.store', $exam)"
                        show-points />
                </div>
            </div>
        </div>
    </div>
</x-layouts.studyai>
