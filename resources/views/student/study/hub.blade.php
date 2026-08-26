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

    <a href="{{ route('student.study.index') }}" class="text-xs text-accent">← All study guides</a>

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
                <div class="lg:col-span-2">
                    {{-- Key terms are rendered by the component, so they are not
                         repeated in the sidebar here. --}}
                    <x-study-guide :guide="$guide"
                                   :sections="$sections"
                                   :key-terms="$keyTerms"
                                   :storage-key="'guide:'.$material->id" />
                </div>

                <div class="space-y-4">
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
            <x-flashcard-deck :cards="$due"
                              :answer-route="route('student.study.answer', ['flashcard' => '__ID__'])" />
        @endif
    </div>

    {{-- ─────────────── Quiz ─────────────── --}}
    <div data-tab-panel="quiz" @style(['display: none' => $tab !== 'quiz'])>
        @if ($material->questions->isEmpty())
            <x-ui.empty icon="clipboard" title="No quiz"
                        message="There are no quiz questions for this material yet." />
        @else
            <x-quiz :questions="$material->questions" />
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

        </script>
    @endpush
</x-layouts.studyai>
