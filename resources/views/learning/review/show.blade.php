@php
    use App\Models\Material;

    $user = auth()->user();
    $canReview = $user->can('review', $material);
    $canEdit = $user->can('update', $material);

    $guide = $material->studyGuide;
    $sections = $guide?->normalisedSections() ?? [];
    $keyTerms = $guide?->normalisedKeyTerms() ?? [];
    $topic = $material->topic;

    $links = $topic
        ? $topic->links->filter(fn ($link) => $link->linkedTopic)
        : collect();

    $awaitingDecision = in_array($material->workflow_state, [
        Material::STATE_SUBMITTED,
        Material::STATE_UNDER_REVIEW,
    ], true);

    $canSubmit = $canEdit && in_array($material->workflow_state, [
        Material::STATE_DRAFT,
        Material::STATE_AI_COMPLETED,
        Material::STATE_CHANGES_REQUESTED,
    ], true);

    $tabs = [
        ['guide', 'Study Guide', 'document', count($sections)],
        ['flashcards', 'Flashcards', 'layers', $material->flashcards->count()],
        ['quiz', 'Quiz', 'clipboard', $material->questions->count()],
        ['source', 'Source', 'book', 0],
        ['notes', 'Notes', 'chat', $material->notes->count()],
        ['links', 'Links', 'link', $links->count()],
    ];

    $tab = request()->query('tab', 'guide');
    $backUrl = $canReview ? route('learning.review') : route('teacher.materials.index');
@endphp

<x-layouts.studyai :title="$material->title">

    <div x-data="{ tab: @js($tab) }">

        {{-- ── Header ── --}}
        <a href="{{ $backUrl }}" class="text-xs text-accent">← Back to study guides queue</a>

        <div class="mt-2 flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <h2 class="font-display text-2xl text-ink">{{ $material->title }}</h2>

                <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted">
                    @if ($material->subject)
                        <span>{{ $material->subject->name }}</span><span class="text-faint">·</span>
                    @endif
                    @if ($material->classArm)
                        <span>{{ $material->classArm->fullName() }}</span><span class="text-faint">·</span>
                    @endif
                    <span>by <span class="font-medium text-ink">{{ $material->creator?->name ?? 'Unknown' }}</span></span>
                    <x-ui.badge :tone="$material->stateTone()">{{ $material->stateLabel() }}</x-ui.badge>
                </div>

                @if ($material->submitted_at)
                    <p class="mt-1 text-xs text-faint">Submitted {{ $material->submitted_at->diffForHumans() }}</p>
                @endif
            </div>

            {{-- Decision actions live in the header, where the reviewer expects them --}}
            <div class="flex items-center gap-1">
                @if ($canReview && $awaitingDecision)
                    <form method="POST" action="{{ route('learning.materials.approve', $material) }}">
                        @csrf
                        <input type="hidden" name="publish" value="1">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <x-icon name="check" /> Approve
                        </button>
                    </form>

                    <button type="button" class="btn btn-ghost btn-sm" @click="$dispatch('open-changes')">
                        <x-icon name="pencil" /> Request Changes
                    </button>

                    <button type="button" class="btn btn-ghost btn-sm text-danger" @click="$dispatch('open-reject')">
                        <x-icon name="x" /> Reject
                    </button>
                @endif

                @if ($canReview && $material->workflow_state === Material::STATE_APPROVED)
                    <form method="POST" action="{{ route('learning.materials.publish', $material) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm">Publish to students</button>
                    </form>
                @endif

                @if ($canReview && $material->isPublished())
                    <form method="POST" action="{{ route('learning.materials.unpublish', $material) }}">
                        @csrf
                        <button type="submit" class="btn btn-outline btn-sm">Unpublish</button>
                    </form>
                @endif

                @if ($canSubmit)
                    <button type="button" class="btn btn-primary btn-sm" @click="$dispatch('open-submit')">
                        Submit for review
                    </button>
                @endif
            </div>
        </div>

        {{-- ── Flash / errors ── --}}
        @if (session('status'))
            <div class="mt-4 rounded-md border border-success/30 bg-success/5 px-4 py-2.5 text-sm text-success">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mt-4 rounded-md border border-danger/30 bg-danger/5 px-4 py-2.5 text-sm text-danger">
                {{ $errors->first() }}
            </div>
        @endif

        @if ($material->isProcessing())
            <div class="mt-4 flex items-center gap-3 rounded-md border border-line bg-surface-raised px-4 py-3 text-sm"
                 x-init="setTimeout(() => window.location.reload(), 8000)">
                <span class="h-2 w-2 shrink-0 animate-pulse rounded-full bg-accent"></span>
                <span class="text-muted">Generating study content. This page refreshes automatically.</span>
            </div>
        @elseif ($material->workflow_state === Material::STATE_AI_FAILED)
            <div class="mt-4 rounded-md border border-danger/30 bg-danger/5 px-4 py-3 text-sm">
                <div class="font-medium text-danger">Generation failed</div>
                <p class="mt-1 text-muted">
                    {{ $material->processingJobs()->latest()->first()?->error ?? 'Something went wrong.' }}
                </p>
            </div>
        @endif

        @if ($material->review_notes && $canEdit && $material->workflow_state === Material::STATE_CHANGES_REQUESTED)
            <div class="mt-4 rounded-md border border-warning/30 bg-warning/5 px-4 py-3 text-sm">
                <div class="font-medium text-ink">Changes requested</div>
                <p class="mt-1 text-muted">{{ $material->review_notes }}</p>
            </div>
        @endif

        {{-- ── Tabs ── --}}
        <div class="mt-5 flex flex-wrap gap-1 border-b border-line">
            @foreach ($tabs as [$key, $label, $icon, $count])
                <button type="button" class="tab-btn flex items-center gap-1.5"
                        :class="tab === '{{ $key }}' ? 'active' : ''"
                        @click="tab = '{{ $key }}'; history.replaceState(null, '', '?tab={{ $key }}')">
                    <x-icon :name="$icon" />
                    {{ $label }}
                    @if ($count > 0)<span class="tnum text-xs text-faint">{{ $count }}</span>@endif
                </button>
            @endforeach
        </div>

        <div class="mt-5">

            {{-- ─────────── Study guide ─────────── --}}
            <div x-show="tab === 'guide'" x-cloak>
                @if (! $guide || (! $sections && ! $guide->summary))
                    <x-ui.empty icon="document" title="No study guide"
                                message="Nothing has been generated for this material yet." />
                @else
                    {{-- Title banner --}}
                    <div class="surface flex items-center gap-4 border-s-2 border-s-success p-5">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-surface-sunk text-muted">
                            <x-icon name="document" />
                        </span>
                        <div class="min-w-0">
                            <h3 class="font-display text-lg text-ink">{{ $guide->displayTitle() }}</h3>
                            <p class="text-xs text-faint">
                                {{ collect([$material->subject?->name, $material->classArm?->fullName()])->filter()->join(' · ') }}
                            </p>
                        </div>
                    </div>

                    @if ($guide->summary)
                        <p class="mt-4 text-sm leading-relaxed text-muted">{{ $guide->summary }}</p>
                    @endif

                    {{-- Numbered section cards --}}
                    <div class="mt-4 space-y-4">
                        @foreach ($sections as $index => $section)
                            <section class="surface p-5">
                                <div class="flex gap-4">
                                    <span class="tnum flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-surface-sunk text-xs font-medium text-muted">
                                        {{ $index + 1 }}
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <h4 class="font-semibold text-ink">{{ $section['heading'] }}</h4>
                                        <x-prose :text="$section['body']" class="mt-2" />
                                    </div>
                                </div>
                            </section>
                        @endforeach
                    </div>

                    @if ($keyTerms)
                        <section class="surface mt-4 p-5">
                            <h4 class="font-semibold text-ink">Key terms</h4>
                            <dl class="mt-3 space-y-2">
                                @foreach ($keyTerms as $term)
                                    <div class="text-sm">
                                        <dt class="inline font-medium text-ink">{{ $term['term'] }}:</dt>
                                        <dd class="inline text-muted"> {{ $term['definition'] }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </section>
                    @endif
                @endif
            </div>

            {{-- ─────────── Flashcards ─────────── --}}
            <div x-show="tab === 'flashcards'" x-cloak>
                @if ($material->flashcards->isEmpty())
                    <x-ui.empty icon="layers" title="No flashcards" message="None have been generated yet." />
                @else
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($material->flashcards as $index => $card)
                            <div class="surface p-4">
                                <div class="mb-2 text-xs text-faint">Card {{ $index + 1 }}</div>
                                <div class="text-sm font-medium text-ink">{{ $card->front }}</div>
                                <div class="mt-2 border-t border-line pt-2 text-sm text-muted">{{ $card->back }}</div>
                                @if ($card->tags)
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        @foreach ($card->tags as $tag)
                                            <span class="badge">{{ $tag }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- ─────────── Quiz ─────────── --}}
            <div x-show="tab === 'quiz'" x-cloak>
                @if ($material->questions->isEmpty())
                    <x-ui.empty icon="clipboard" title="No quiz" message="No questions have been generated yet." />
                @else
                    <ol class="space-y-3">
                        @foreach ($material->questions as $i => $question)
                            <li class="surface p-4">
                                <div class="flex gap-3">
                                    <span class="tnum flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-surface-sunk text-xs text-muted">
                                        {{ $i + 1 }}
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm font-medium text-ink">{{ $question->question }}</div>

                                        <ul class="mt-2 space-y-1">
                                            @foreach ((array) $question->options as $index => $option)
                                                @php $isCorrect = $index === $question->correct_idx; @endphp
                                                <li class="flex items-start gap-2 text-sm {{ $isCorrect ? 'font-medium text-success' : 'text-muted' }}">
                                                    <span class="w-4 shrink-0 text-xs">{{ $isCorrect ? '✓' : chr(65 + $index) }}</span>
                                                    <span>{{ $option }}</span>
                                                </li>
                                            @endforeach
                                        </ul>

                                        @if ($question->explanation)
                                            <p class="mt-2 border-t border-line pt-2 text-xs text-faint">
                                                {{ $question->explanation }}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>

            {{-- ─────────── Source ─────────── --}}
            <div x-show="tab === 'source'" x-cloak>
                <div class="surface p-5">
                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-faint">Type</dt>
                            <dd class="text-ink">{{ $material->type }}</dd>
                        </div>
                        @if ($material->file_name)
                            <div>
                                <dt class="text-xs text-faint">File</dt>
                                <dd class="text-ink">
                                    {{ $material->file_name }}
                                    @if ($material->file_size)
                                        <span class="text-faint">({{ round($material->file_size / 1024) }} KB)</span>
                                    @endif
                                </dd>
                            </div>
                        @endif
                        @if ($material->source_url)
                            <div class="sm:col-span-2">
                                <dt class="text-xs text-faint">Link</dt>
                                <dd><a href="{{ $material->source_url }}" target="_blank" rel="noopener"
                                       class="break-all text-accent">{{ $material->source_url }}</a></dd>
                            </div>
                        @endif
                    </dl>

                    @if ($material->description)
                        <div class="mt-4 border-t border-line pt-4">
                            <div class="text-xs text-faint">Description</div>
                            <p class="mt-1 text-sm text-muted">{{ $material->description }}</p>
                        </div>
                    @endif

                    @if ($material->sourceText())
                        <details class="mt-4 border-t border-line pt-4">
                            <summary class="cursor-pointer text-sm text-accent">Extracted text</summary>
                            <pre class="mt-2 max-h-96 overflow-auto whitespace-pre-wrap rounded-md bg-surface-sunk p-3 text-xs text-muted">{{ $material->sourceText() }}</pre>
                        </details>
                    @endif
                </div>
            </div>

            {{-- ─────────── Notes ─────────── --}}
            <div x-show="tab === 'notes'" x-cloak>
                <div class="max-w-2xl space-y-4">
                    @if ($material->notes->isEmpty())
                        <x-ui.empty icon="chat" title="No history yet"
                                    message="Submissions, approvals and change requests appear here." />
                    @else
                        <ol class="space-y-3">
                            @foreach ($material->notes as $note)
                                <li class="surface p-4">
                                    <div class="flex items-center gap-2">
                                        <x-ui.badge :tone="$note->tone()">{{ $note->label() }}</x-ui.badge>
                                        <span class="text-xs text-faint">
                                            {{ $note->user?->name ?? 'System' }} · {{ $note->created_at->diffForHumans() }}
                                        </span>
                                    </div>
                                    <p class="mt-2 text-sm text-muted">{{ $note->content }}</p>
                                </li>
                            @endforeach
                        </ol>
                    @endif

                    <form method="POST" action="{{ route('learning.materials.notes', $material) }}"
                          class="surface space-y-2 p-4">
                        @csrf
                        <textarea name="content" rows="3" required
                                  class="w-full rounded-md border border-line bg-surface px-3 py-2 text-sm"
                                  placeholder="Add a note…"></textarea>
                        <x-ui.button type="submit" variant="outline" size="sm">Add note</x-ui.button>
                    </form>
                </div>
            </div>

            {{-- ─────────── Links ─────────── --}}
            <div x-show="tab === 'links'" x-cloak>
                @if ($links->isEmpty())
                    <x-ui.empty icon="link" title="No linked topics"
                                message="Related topics appear here once other material in this subject has been processed." />
                @else
                    <div class="surface divide-y divide-line">
                        @foreach ($links as $link)
                            <div class="flex items-center justify-between gap-4 px-4 py-3">
                                <span class="truncate text-sm text-ink">{{ $link->linkedTopic->name }}</span>
                                <span class="shrink-0 text-xs text-faint">
                                    {{ $link->label() }}
                                    @if ($link->is_manual)
                                        · added by a teacher
                                    @else
                                        · {{ $link->confidencePercent() }}% confidence
                                    @endif
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- ── Modals ── --}}
        @if ($canReview && $awaitingDecision)
            <div x-data="{ open: false }" @open-changes.window="open = true" x-cloak>
                <div x-show="open" class="fixed inset-0 z-40 bg-black/40" @click="open = false"></div>
                <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="surface w-full max-w-md p-5" @click.outside="open = false">
                        <h3 class="font-semibold text-ink">Request changes</h3>
                        <p class="mt-1 text-sm text-muted">The teacher sees this note and can revise and resubmit.</p>
                        <form method="POST" action="{{ route('learning.materials.request-changes', $material) }}"
                              class="mt-4 space-y-3">
                            @csrf
                            <textarea name="note" rows="4" required autofocus
                                      class="w-full rounded-md border border-line bg-surface px-3 py-2 text-sm"
                                      placeholder="What needs to change?"></textarea>
                            <div class="flex justify-end gap-2">
                                <button type="button" class="btn btn-ghost btn-sm" @click="open = false">Cancel</button>
                                <button type="submit" class="btn btn-primary btn-sm">Send back</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div x-data="{ open: false }" @open-reject.window="open = true" x-cloak>
                <div x-show="open" class="fixed inset-0 z-40 bg-black/40" @click="open = false"></div>
                <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="surface w-full max-w-md p-5" @click.outside="open = false">
                        <h3 class="font-semibold text-ink">Reject this material</h3>
                        <p class="mt-1 text-sm text-muted">Give a reason so the teacher knows why.</p>
                        <form method="POST" action="{{ route('learning.materials.reject', $material) }}"
                              class="mt-4 space-y-3">
                            @csrf
                            <textarea name="reason" rows="4" required autofocus
                                      class="w-full rounded-md border border-line bg-surface px-3 py-2 text-sm"
                                      placeholder="Why is this being rejected?"></textarea>
                            <div class="flex justify-end gap-2">
                                <button type="button" class="btn btn-ghost btn-sm" @click="open = false">Cancel</button>
                                <button type="submit" class="btn btn-danger btn-sm">Confirm rejection</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        @if ($canSubmit)
            <div x-data="{ open: false }" @open-submit.window="open = true" x-cloak>
                <div x-show="open" class="fixed inset-0 z-40 bg-black/40" @click="open = false"></div>
                <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="surface w-full max-w-md p-5" @click.outside="open = false">
                        <h3 class="font-semibold text-ink">Submit for review</h3>
                        <p class="mt-1 text-sm text-muted">An administrator will approve or send it back.</p>
                        <form method="POST" action="{{ route('learning.materials.submit', $material) }}"
                              class="mt-4 space-y-3">
                            @csrf
                            <textarea name="note" rows="3"
                                      class="w-full rounded-md border border-line bg-surface px-3 py-2 text-sm"
                                      placeholder="Anything the reviewer should know? (optional)"></textarea>
                            <div class="flex justify-end gap-2">
                                <button type="button" class="btn btn-ghost btn-sm" @click="open = false">Cancel</button>
                                <button type="submit" class="btn btn-primary btn-sm">Submit</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-layouts.studyai>
