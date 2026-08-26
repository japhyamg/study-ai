@props([
    'cards',
    'answerRoute' => null,
    'total' => null,
])

@php
    /**
     * Flashcard review.
     *
     * Two modes:
     *
     *   - with $answerRoute: each rating POSTs so SM-2 scheduling is recorded.
     *     Spaced repetition is the point of this feature, so the write is not
     *     optional — but the card advances optimistically first, so the review
     *     never waits on the round trip.
     *   - without: a read-only preview (teacher review), no scheduling.
     */
    $deck = collect($cards)->map(fn ($card) => [
        'id' => $card->id,
        'front' => (string) $card->front,
        'back' => (string) $card->back,
        'tags' => array_values((array) ($card->tags ?? [])),
    ])->values();

    $count = $total ?? $deck->count();
@endphp

<div class="mx-auto max-w-2xl"
     x-data="flashcardDeck({
        cards: @js($deck),
        answerUrlTemplate: @js($answerRoute),
     })"
     @keydown.window="onKey($event)">

    {{-- ── Progress ── --}}
    <div class="mb-3 flex items-center justify-between gap-3 text-sm">
        <span class="text-muted">
            Card <span class="tnum" x-text="index + 1"></span> of <span class="tnum">{{ $count }}</span>
            <span class="text-faint" x-show="reviewed.size > 0" x-cloak>
                · <span class="tnum" x-text="reviewed.size"></span> reviewed
            </span>
        </span>

        <div class="hidden h-1.5 w-32 overflow-hidden rounded-full bg-surface-sunk sm:block">
            <div class="h-full rounded-full bg-success transition-all duration-300"
                 :style="`width: ${percent()}%`"></div>
        </div>
    </div>

    {{-- ── The card ── --}}
    <div class="flashcard surface" :class="{ 'flipped': flipped }" @click="flip()"
         role="button" tabindex="0" :aria-label="flipped ? 'Showing answer' : 'Showing question'"
         @keydown.enter.prevent="flip()">
        <div class="flashcard-inner">
            <div class="flashcard-face flashcard-front">
                <div>
                    <p class="text-xs uppercase tracking-wide text-faint">Question</p>
                    <p class="mt-3 whitespace-pre-line text-lg font-medium text-ink" x-text="card.front"></p>
                    <p class="mt-4 text-xs text-faint">Click or press Space to flip</p>
                </div>
            </div>
            <div class="flashcard-face flashcard-back">
                <div>
                    <p class="text-xs uppercase tracking-wide text-success">Answer</p>
                    <p class="mt-3 whitespace-pre-line text-lg font-medium text-ink" x-text="card.back"></p>
                    <p class="mt-4 text-xs text-faint">Click to flip back</p>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-2 flex flex-wrap gap-1" x-show="card.tags.length" x-cloak>
        <template x-for="tag in card.tags" :key="tag">
            <span class="badge" x-text="tag"></span>
        </template>
    </div>

    @if ($answerRoute)
        {{-- ── Rating: only meaningful once the answer is visible ── --}}
        <div class="mt-4" x-show="flipped" x-cloak
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            <p class="mb-2 text-xs text-muted">How well did you know it?</p>
            <div class="grid grid-cols-4 gap-2">
                @foreach ([
                    ['0', 'Again', 'border-danger/40 text-danger hover:bg-danger/10'],
                    ['3', 'Hard', 'border-warning/40 text-warning hover:bg-warning/10'],
                    ['4', 'Good', 'border-info/40 text-info hover:bg-info/10'],
                    ['5', 'Easy', 'border-success/40 text-success hover:bg-success/10'],
                ] as $i => [$quality, $label, $tone])
                    <button type="button"
                            class="rounded-md border bg-transparent px-2 py-2 text-sm font-medium transition-colors {{ $tone }}"
                            @click="rate({{ $quality }})">
                        {{ $label }}
                        <span class="mt-0.5 block text-[0.6875rem] font-normal opacity-60">{{ $i + 1 }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        <button type="button" class="btn btn-ghost mt-3" x-show="! flipped" @click="flip()">
            Show answer
        </button>

        {{-- Ratings post in the background; this only surfaces a failure. --}}
        <p class="mt-2 text-xs text-danger" x-show="error" x-cloak x-text="error"></p>
    @endif

    {{-- ── Navigation ── --}}
    <div class="mt-4 flex items-center justify-between">
        <button type="button" class="btn btn-outline btn-sm" :disabled="index === 0" @click="go(index - 1)">
            <x-icon name="chevron-left" /> Previous
        </button>

        <span class="text-xs text-faint">1–4 to rate · ← → to move</span>

        <button type="button" class="btn btn-outline btn-sm"
                :disabled="index >= cards.length - 1" @click="go(index + 1)">
            Next <x-icon name="chevron-right" />
        </button>
    </div>

    {{-- ── Finished ── --}}
    <div class="mt-4 rounded-lg border border-success/30 bg-success/5 p-6 text-center"
         x-show="reviewed.size === cards.length && cards.length > 0" x-cloak>
        <p class="font-medium text-success">All cards reviewed</p>
        <p class="mt-1 text-sm text-muted">
            You've been through all <span class="tnum" x-text="cards.length"></span> cards.
        </p>
        <button type="button" class="btn btn-outline btn-sm mt-3" @click="restart()">Start over</button>
    </div>
</div>

@once
    @push('scripts')
        <script>
            function flashcardDeck({ cards, answerUrlTemplate }) {
                return {
                    cards,
                    answerUrlTemplate,
                    index: 0,
                    flipped: false,
                    reviewed: new Set(),
                    error: '',

                    get card() {
                        return this.cards[this.index] ?? { front: '', back: '', tags: [] };
                    },

                    percent() {
                        return this.cards.length === 0
                            ? 0
                            : Math.round((this.reviewed.size / this.cards.length) * 100);
                    },

                    flip() { this.flipped = ! this.flipped; },

                    /**
                     * Move to another card.
                     *
                     * Un-flip BEFORE the text changes, or the next card's answer
                     * is briefly visible through the flip animation. Everything
                     * that changes `index` goes through here for that reason.
                     */
                    go(next) {
                        if (next < 0 || next >= this.cards.length) return;

                        if (! this.flipped) {
                            this.index = next;
                            return;
                        }

                        this.flipped = false;
                        setTimeout(() => { this.index = next; }, 350);
                    },

                    /**
                     * Record a rating and move on.
                     *
                     * The card advances immediately and the write happens in the
                     * background: SM-2 scheduling matters, but making the reader
                     * wait for a round trip on every card does not. A failure is
                     * surfaced rather than silently dropped.
                     */
                    rate(quality) {
                        const card = this.card;
                        if (! card.id) return;

                        this.reviewed = new Set(this.reviewed).add(card.id);
                        this.error = '';

                        if (this.answerUrlTemplate) {
                            const url = this.answerUrlTemplate.replace('__ID__', card.id);

                            fetch(url, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                                },
                                body: JSON.stringify({ quality }),
                            }).catch(() => {
                                this.error = 'That rating could not be saved — check your connection.';
                            });
                        }

                        if (this.index < this.cards.length - 1) {
                            this.go(this.index + 1);
                        } else {
                            this.flipped = false;
                        }
                    },

                    restart() {
                        this.reviewed = new Set();
                        this.flipped = false;
                        this.index = 0;
                    },

                    onKey(event) {
                        const tag = event.target.tagName;
                        if (tag === 'INPUT' || tag === 'TEXTAREA' || event.target.isContentEditable) return;

                        // The listener is on window, so it also fires while the
                        // deck sits in a hidden tab. offsetParent is null for a
                        // display:none subtree — without this, Space would flip
                        // an invisible card while the reader is on another tab.
                        if (this.$el.offsetParent === null) return;

                        if (event.code === 'Space') {
                            event.preventDefault();
                            this.flip();
                            return;
                        }
                        if (event.key === 'ArrowRight') { event.preventDefault(); this.go(this.index + 1); return; }
                        if (event.key === 'ArrowLeft') { event.preventDefault(); this.go(this.index - 1); return; }

                        if (! this.flipped) return;

                        // 1–4 map to the SM-2 qualities behind Again/Hard/Good/Easy.
                        const quality = { '1': 0, '2': 3, '3': 4, '4': 5 }[event.key];
                        if (quality !== undefined) {
                            event.preventDefault();
                            this.rate(quality);
                        }
                    },
                };
            }
        </script>
    @endpush
@endonce
