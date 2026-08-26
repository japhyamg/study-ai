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
    $bankCount = $questionBank->flatten()->count();
@endphp

<x-layouts.studyai title="Exams" :back-to="route('teacher.exams.index')" back-label="All exams">

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

    {{-- ── Details and actions ──
         Kept on the page rather than in the topbar: these belong to the exam,
         not to the app chrome, and publishing wants the question count next to
         it to make sense. --}}
    <div class="surface mb-6">
        <div class="flex flex-wrap items-start justify-between gap-4 px-5 py-4">
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <h2 class="font-display text-lg text-ink">{{ $exam->title }}</h2>
                    <span class="{{ $published ? 'badge badge-success' : 'badge' }}">
                        {{ $published ? 'Published' : 'Draft' }}
                    </span>
                </div>

                @if ($exam->description)
                    <p class="mt-1 max-w-prose text-sm text-muted">{{ $exam->description }}</p>
                @endif
            </div>

            <div class="flex shrink-0 items-center gap-2">
                <x-ui.button href="{{ route('teacher.exams.edit', $exam) }}" variant="ghost" size="sm">
                    Edit settings
                </x-ui.button>

                @if ($published)
                    <form method="POST" action="{{ route('teacher.exams.unpublish', $exam) }}">
                        @csrf @method('PUT')
                        <x-ui.button type="submit" variant="ghost" size="sm">Unpublish</x-ui.button>
                    </form>
                @else
                    <form method="POST" action="{{ route('teacher.exams.publish', $exam) }}">
                        @csrf @method('PUT')
                        <x-ui.button type="submit" size="sm" :disabled="$questions->isEmpty()">
                            Publish
                        </x-ui.button>
                    </form>
                @endif

                @can('delete', $exam)
                    <form method="POST" action="{{ route('teacher.exams.destroy', $exam) }}"
                          onsubmit="return confirm('Delete this exam and its questions? This cannot be undone.')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn-icon text-muted hover:text-danger" title="Delete exam">
                            <x-icon name="trash" />
                        </button>
                    </form>
                @endcan
            </div>
        </div>

        {{-- The settings that decide how it behaves, so they can be checked
             without opening the edit form. --}}
        <dl class="grid grid-cols-2 gap-x-6 gap-y-3 border-t border-line px-5 py-4 text-sm sm:grid-cols-4">
            <div>
                <dt class="text-xs text-faint">Questions</dt>
                <dd class="text-ink"><span class="tnum">{{ $questions->count() }}</span>
                    · <span class="tnum">{{ $totalPoints }}</span> {{ Str::plural('pt', $totalPoints) }}</dd>
            </div>

            <div>
                <dt class="text-xs text-faint">Duration</dt>
                <dd class="text-ink">{{ $exam->duration ? $exam->duration.' min' : 'Untimed' }}</dd>
            </div>

            <div>
                <dt class="text-xs text-faint">Pass mark</dt>
                <dd class="text-ink"><span class="tnum">{{ (int) $exam->pass_mark }}</span>%</dd>
            </div>

            <div>
                <dt class="text-xs text-faint">Attempts</dt>
                <dd class="text-ink tnum">{{ $exam->max_attempts }}</dd>
            </div>

            <div class="col-span-2">
                <dt class="text-xs text-faint">Availability</dt>
                <dd class="text-ink">
                    @if ($exam->start_time || $exam->end_time)
                        {{ $exam->start_time?->format('j M Y, g:ia') ?? 'Open now' }}
                        → {{ $exam->end_time?->format('j M Y, g:ia') ?? 'no closing time' }}
                    @else
                        Always open once published
                    @endif
                </dd>
            </div>

            <div class="col-span-2">
                <dt class="text-xs text-faint">Shuffling</dt>
                <dd class="text-ink">
                    @if ($exam->shuffle_questions || $exam->shuffle_options)
                        {{ collect(['Questions' => $exam->shuffle_questions, 'options' => $exam->shuffle_options])
                            ->filter()->keys()->join(' and ') }}
                    @else
                        Off — everyone sees the same order
                    @endif
                </dd>
            </div>
        </dl>
    </div>

    {{-- ── Questions ── --}}
    <div x-data="{ adding: false, editing: null }">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="font-display text-base text-ink">Questions</h3>
                <p class="text-xs text-faint">
                    @if ($questions->isEmpty())
                        Add at least one question before publishing.
                    @else
                        Shown in the order students will see them.
                    @endif
                </p>
            </div>

            <div class="flex items-center gap-2">
                @if ($bankCount)
                    <x-ui.button type="button" variant="ghost" size="sm"
                                 x-on:click="adding = adding === 'bank' ? false : 'bank'">
                        From bank ({{ $bankCount }})
                    </x-ui.button>
                @endif

                <x-ui.button type="button" size="sm" icon="plus"
                             x-on:click="adding = adding === 'new' ? false : 'new'">
                    Write a question
                </x-ui.button>
            </div>
        </div>

        {{-- Add panels sit directly above the list, so a newly added question
             appears right where the teacher is already looking. --}}
        <div x-show="adding === 'bank'" x-cloak class="surface mb-4 p-5"
             @bank-form-cancel="adding = false">
            <div class="mb-1 font-medium text-ink">Add from the subject bank</div>

            @if ($questionBank->isEmpty())
                <p class="text-xs text-faint">
                    @if (! $exam->subject_id)
                        Set a subject in the exam settings to pull from its bank.
                    @else
                        Nothing available yet. Questions join the bank once an admin
                        approves the study guide they came from.
                    @endif
                </p>
            @else
                <p class="mb-3 text-xs text-faint">
                    {{ $bankCount }} approved {{ Str::plural('question', $bankCount) }} for
                    {{ $exam->subject?->name ?? 'this subject' }}, grouped by study guide.
                </p>

                <form method="POST" action="{{ route('teacher.exams.questions.from-bank', $exam) }}"
                      x-data="{ picked: 0 }">
                    @csrf
                    <div class="max-h-96 space-y-4 overflow-y-auto pe-1">
                        @foreach ($questionBank as $topic => $rows)
                            <div>
                                <p class="mb-1.5 text-xs font-medium text-muted">{{ $topic }}</p>
                                <div class="space-y-1">
                                    @foreach ($rows as $row)
                                        <label class="flex cursor-pointer items-start gap-2.5 rounded-md p-2 text-sm transition-colors hover:bg-surface-sunk">
                                            <input type="checkbox" name="bank_ids[]" value="{{ $row->id }}"
                                                   class="checkbox mt-0.5 shrink-0"
                                                   x-on:change="picked += $event.target.checked ? 1 : -1">
                                            <span class="min-w-0">
                                                <span class="block text-ink">{{ $row->question }}</span>
                                                <span class="mt-0.5 block text-xs text-faint">
                                                    {{ $typeLabels[$row->type] ?? $row->type }}
                                                </span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 flex justify-end gap-2 border-t border-line pt-3">
                        <x-ui.button type="button" variant="ghost" size="sm" x-on:click="adding = false">
                            Cancel
                        </x-ui.button>
                        <button type="submit" class="btn btn-primary btn-sm" :disabled="picked === 0">
                            Add <span x-show="picked > 0" x-cloak><span x-text="picked"></span></span> selected
                        </button>
                    </div>
                </form>
            @endif
        </div>

        <div x-show="adding === 'new'" x-cloak class="surface mb-4 p-5"
             @bank-form-cancel="adding = false">
            <p class="mb-3 text-xs text-faint">
                Added to this exam only. It does not join the subject bank.
            </p>

            <x-bank-question-form
                :action="route('teacher.exams.questions.store', $exam)"
                show-points />
        </div>

        @if ($questions->isEmpty())
            <x-ui.empty icon="clipboard" title="No questions yet"
                        message="Add them from the subject's bank, or write your own." />
        @else
            <ol class="space-y-2">
                @foreach ($questions as $q)
                    @php
                        $opts = is_array($q->options) ? $q->options : [];
                    @endphp

                    <li class="surface" @bank-form-cancel.stop="editing = null">
                        {{-- Reading view --}}
                        <div x-show="editing !== '{{ $q->id }}'" class="px-5 py-4">
                            <div class="flex items-start gap-3">
                                <span class="tnum mt-0.5 w-5 shrink-0 text-sm text-faint">{{ $loop->iteration }}</span>

                                <div class="min-w-0 flex-1">
                                    <p class="text-sm text-ink">{{ $q->question }}</p>

                                    @if ($opts)
                                        <ul class="mt-2 space-y-1">
                                            @foreach ($opts as $opt)
                                                @php $isAnswer = (string) $opt === (string) $q->answer; @endphp
                                                <li class="flex items-start gap-2 text-sm">
                                                    <span class="mt-px shrink-0 {{ $isAnswer ? 'text-success' : 'text-faint' }}">
                                                        {{ $isAnswer ? '✓' : '·' }}
                                                    </span>
                                                    <span class="{{ $isAnswer ? 'text-ink' : 'text-muted' }}">{{ $opt }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <p class="mt-2 text-sm">
                                            <span class="text-faint">Answer:</span>
                                            <span class="text-ink">{{ $q->answer }}</span>
                                        </p>
                                    @endif

                                    @if ($q->explanation)
                                        <p class="mt-2 text-xs text-muted">{{ $q->explanation }}</p>
                                    @endif

                                    <div class="mt-2.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-faint">
                                        <span>{{ $typeLabels[$q->type] ?? $q->type }}</span>
                                        <span>·</span>
                                        <span><span class="tnum">{{ $q->points ?? 1 }}</span> {{ Str::plural('pt', $q->points ?? 1) }}</span>
                                        @if ($q->bank_id)
                                            <span>·</span>
                                            <span>From bank</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex shrink-0 items-center gap-1">
                                    <button type="button" class="btn-icon text-muted hover:text-ink"
                                            x-on:click="editing = '{{ $q->id }}'" title="Edit question">
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
                        </div>

                        {{-- Editing view --}}
                        <div x-show="editing === '{{ $q->id }}'" x-cloak class="px-5 py-4">
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
</x-layouts.studyai>
