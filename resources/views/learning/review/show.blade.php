@php
    $user = auth()->user();
    $canReview = $user->can('review', $material);
    $canEdit = $user->can('update', $material);
    $guide = $material->studyGuide;
    $sections = $guide?->normalisedSections() ?? [];
    $keyTerms = $guide?->normalisedKeyTerms() ?? [];
@endphp

<x-layouts.studyai :title="$material->title"
                   :subtitle="collect([
                        $material->subject?->name,
                        $material->classArm?->fullName(),
                        $material->creator?->name,
                   ])->filter()->join(' · ')">

    <x-slot:actions>
        <x-ui.badge :tone="$material->stateTone()">{{ $material->stateLabel() }}</x-ui.badge>
    </x-slot:actions>

    @if (session('status'))
        <div class="mb-4 rounded-md border border-success/30 bg-success/5 px-4 py-2.5 text-sm text-success">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-md border border-danger/30 bg-danger/5 px-4 py-2.5 text-sm text-danger">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Processing / failure banners --}}
    @if ($material->isProcessing())
        <div class="mb-5 flex items-center gap-3 rounded-md border border-line bg-surface-raised px-4 py-3 text-sm"
             x-data="{}" x-init="setTimeout(() => window.location.reload(), 8000)">
            <span class="h-2 w-2 shrink-0 animate-pulse rounded-full bg-accent"></span>
            <span class="text-muted">Generating study content. This page refreshes automatically.</span>
        </div>
    @elseif ($material->workflow_state === \App\Models\Material::STATE_AI_FAILED)
        <div class="mb-5 rounded-md border border-danger/30 bg-danger/5 px-4 py-3 text-sm">
            <div class="font-medium text-danger">Generation failed</div>
            <p class="mt-1 text-muted">
                {{ $material->processingJobs()->latest()->first()?->error ?? 'Something went wrong.' }}
            </p>
        </div>
    @endif

    <div class="grid gap-5 lg:grid-cols-3">
        {{-- ── Generated content ── --}}
        <div class="space-y-5 lg:col-span-2">
            <x-ui.card title="Study guide"
                       :subtitle="$sections ? count($sections).' sections' : null">
                @if (! $guide)
                    <x-ui.empty message="No study guide yet." />
                @else
                    @if ($guide->summary)
                        <p class="text-sm leading-relaxed text-muted">{{ $guide->summary }}</p>
                    @endif

                    @if ($sections)
                        <div class="mt-4 space-y-3" x-data="{ open: 0 }">
                            @foreach ($sections as $index => $section)
                                <div class="rounded-md border border-line">
                                    <button type="button" class="flex w-full items-center justify-between px-4 py-2.5 text-left"
                                            @click="open = open === {{ $index }} ? null : {{ $index }}">
                                        <span class="text-sm font-medium text-ink">{{ $section['heading'] }}</span>
                                        <span class="text-faint" :class="open === {{ $index }} ? 'rotate-90' : ''"
                                              style="transition: transform .15s">
                                            <x-icon name="chevron-right" />
                                        </span>
                                    </button>
                                    <div x-show="open === {{ $index }}" x-cloak>
                                        <div class="whitespace-pre-line border-t border-line px-4 py-3 text-sm leading-relaxed text-muted">{{ $section['body'] }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if ($keyTerms)
                        <div class="mt-5 border-t border-line pt-4">
                            <div class="mb-2 text-xs font-medium uppercase tracking-wide text-faint">Key terms</div>
                            <dl class="space-y-2">
                                @foreach ($keyTerms as $term)
                                    <div class="text-sm">
                                        <dt class="inline font-medium text-ink">{{ $term['term'] }}:</dt>
                                        <dd class="inline text-muted"> {{ $term['definition'] }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    @endif
                @endif
            </x-ui.card>

            <x-ui.card title="Flashcards" :subtitle="$material->flashcards->count().' cards'">
                @if ($material->flashcards->isEmpty())
                    <x-ui.empty message="No flashcards yet." />
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($material->flashcards as $card)
                            <li class="grid gap-2 py-2.5 text-sm sm:grid-cols-2 sm:gap-4">
                                <div class="text-ink">{{ $card->front }}</div>
                                <div class="text-muted">{{ $card->back }}</div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>

            <x-ui.card title="Quiz" :subtitle="$material->questions->count().' questions'">
                @if ($material->questions->isEmpty())
                    <x-ui.empty message="No quiz questions yet." />
                @else
                    <ol class="space-y-4">
                        @foreach ($material->questions as $i => $question)
                            <li class="text-sm">
                                <div class="font-medium text-ink">{{ $i + 1 }}. {{ $question->question }}</div>

                                <ul class="mt-1.5 space-y-1">
                                    @foreach ((array) $question->options as $index => $option)
                                        @php $isCorrect = $index === $question->correct_idx; @endphp
                                        <li class="flex items-start gap-2 {{ $isCorrect ? 'text-success' : 'text-muted' }}">
                                            <span class="mt-0.5 w-4 shrink-0 text-xs">
                                                {{ $isCorrect ? '✓' : chr(65 + $index) }}
                                            </span>
                                            <span>{{ $option }}</span>
                                        </li>
                                    @endforeach
                                </ul>

                                @if ($question->explanation)
                                    <p class="mt-1.5 text-xs text-faint">{{ $question->explanation }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-ui.card>
        </div>

        {{-- ── Sidebar: actions + trail ── --}}
        <div class="space-y-5">
            @if ($canReview && in_array($material->workflow_state, [
                \App\Models\Material::STATE_SUBMITTED,
                \App\Models\Material::STATE_UNDER_REVIEW,
            ], true))
                <x-ui.card title="Decision">
                    <div class="space-y-3" x-data="{ panel: null }">
                        <form method="POST" action="{{ route('learning.materials.approve', $material) }}">
                            @csrf
                            <input type="hidden" name="publish" value="1">
                            <x-ui.button type="submit" class="w-full">Approve &amp; publish</x-ui.button>
                        </form>

                        <button type="button" class="btn btn-outline w-full"
                                @click="panel = panel === 'changes' ? null : 'changes'">
                            Request changes
                        </button>

                        <div x-show="panel === 'changes'" x-cloak>
                            <form method="POST" action="{{ route('learning.materials.request-changes', $material) }}"
                                  class="space-y-2">
                                @csrf
                                <textarea name="note" rows="3" required
                                          class="w-full rounded-md border border-line bg-surface px-3 py-2 text-sm"
                                          placeholder="What should the teacher change?"></textarea>
                                <x-ui.button type="submit" variant="outline" size="sm" class="w-full">Send back</x-ui.button>
                            </form>
                        </div>

                        <button type="button" class="btn btn-ghost w-full text-danger"
                                @click="panel = panel === 'reject' ? null : 'reject'">
                            Reject
                        </button>

                        <div x-show="panel === 'reject'" x-cloak>
                            <form method="POST" action="{{ route('learning.materials.reject', $material) }}"
                                  class="space-y-2">
                                @csrf
                                <textarea name="reason" rows="3" required
                                          class="w-full rounded-md border border-line bg-surface px-3 py-2 text-sm"
                                          placeholder="Why is this being rejected?"></textarea>
                                <x-ui.button type="submit" variant="danger" size="sm" class="w-full">Confirm rejection</x-ui.button>
                            </form>
                        </div>
                    </div>
                </x-ui.card>
            @endif

            @if ($canReview && $material->workflow_state === \App\Models\Material::STATE_APPROVED)
                <x-ui.card title="Approved">
                    <p class="mb-3 text-sm text-muted">Students cannot see this until it is published.</p>
                    <form method="POST" action="{{ route('learning.materials.publish', $material) }}">
                        @csrf
                        <x-ui.button type="submit" class="w-full">Publish to students</x-ui.button>
                    </form>
                </x-ui.card>
            @endif

            @if ($canReview && $material->isPublished())
                <x-ui.card title="Published">
                    <p class="mb-3 text-sm text-muted">Live for students since {{ $material->published_at?->diffForHumans() }}.</p>
                    <form method="POST" action="{{ route('learning.materials.unpublish', $material) }}">
                        @csrf
                        <x-ui.button type="submit" variant="outline" class="w-full">Unpublish</x-ui.button>
                    </form>
                </x-ui.card>
            @endif

            {{-- Teacher-side actions --}}
            @if ($canEdit && in_array($material->workflow_state, [
                \App\Models\Material::STATE_DRAFT,
                \App\Models\Material::STATE_AI_COMPLETED,
                \App\Models\Material::STATE_CHANGES_REQUESTED,
            ], true))
                <x-ui.card title="Ready to submit?">
                    @if ($material->review_notes)
                        <div class="mb-3 rounded-md border border-warning/30 bg-warning/5 px-3 py-2 text-xs text-muted">
                            <span class="font-medium text-ink">Requested changes:</span> {{ $material->review_notes }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('learning.materials.submit', $material) }}" class="space-y-2">
                        @csrf
                        <textarea name="note" rows="2"
                                  class="w-full rounded-md border border-line bg-surface px-3 py-2 text-sm"
                                  placeholder="Anything the reviewer should know? (optional)"></textarea>
                        <x-ui.button type="submit" class="w-full">Submit for review</x-ui.button>
                    </form>
                </x-ui.card>
            @endif

            @if ($canEdit && ! $material->isProcessing())
                <x-ui.card title="Regenerate">
                    <form method="POST" action="{{ route('learning.materials.regenerate', $material) }}"
                          class="space-y-2">
                        @csrf
                        <select name="type" class="w-full rounded-md border border-line bg-surface px-3 py-2 text-sm">
                            <option value="generate_all">Everything</option>
                            <option value="generate_study_guide">Study guide only</option>
                            <option value="generate_flashcards">Flashcards only</option>
                            <option value="generate_questions">Quiz only</option>
                        </select>
                        <x-ui.button type="submit" variant="outline" size="sm" class="w-full">Run again</x-ui.button>
                        <p class="text-xs text-faint">Replaces the existing content for whichever type you pick.</p>
                    </form>
                </x-ui.card>
            @endif

            {{-- Topic graph --}}
            @if ($material->topic && $material->topic->links->isNotEmpty())
                <x-ui.card title="Related topics">
                    <ul class="space-y-2">
                        @foreach ($material->topic->links as $link)
                            @continue(! $link->linkedTopic)
                            <li class="flex items-center justify-between gap-2 text-sm">
                                <span class="truncate text-muted">{{ $link->linkedTopic->name }}</span>
                                <span class="shrink-0 text-xs text-faint">
                                    {{ $link->label() }}@if (! $link->is_manual) · {{ $link->confidencePercent() }}%@endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif

            {{-- Review trail --}}
            <x-ui.card title="History">
                @if ($material->notes->isEmpty())
                    <x-ui.empty message="Nothing yet." />
                @else
                    <ol class="space-y-3">
                        @foreach ($material->notes as $note)
                            <li class="text-sm">
                                <div class="flex items-center gap-2">
                                    <x-ui.badge :tone="$note->tone()">{{ $note->label() }}</x-ui.badge>
                                    <span class="text-xs text-faint">{{ $note->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="mt-1 text-muted">{{ $note->content }}</p>
                                <p class="text-xs text-faint">{{ $note->user?->name ?? 'System' }}</p>
                            </li>
                        @endforeach
                    </ol>
                @endif

                <form method="POST" action="{{ route('learning.materials.notes', $material) }}"
                      class="mt-4 space-y-2 border-t border-line pt-4">
                    @csrf
                    <textarea name="content" rows="2" required
                              class="w-full rounded-md border border-line bg-surface px-3 py-2 text-sm"
                              placeholder="Add a note…"></textarea>
                    <x-ui.button type="submit" variant="ghost" size="sm">Add note</x-ui.button>
                </form>
            </x-ui.card>
        </div>
    </div>
</x-layouts.studyai>
