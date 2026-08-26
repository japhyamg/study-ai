@props([
    'guide',
    'sections' => [],
    'keyTerms' => [],
    'subtitle' => null,
    'storageKey' => null,
])

@php
    /**
     * Interactive study guide.
     *
     * A generated guide is something a student works *through*, not a page they
     * scroll once, so it carries its own state: collapsible sections, a
     * per-section done marker, reading progress and search. Progress is kept in
     * localStorage rather than the database — it is a private reading aid, not
     * assessment data, and it should not create a write on every click.
     *
     * Section icons are derived from the heading. The AI is not asked for them,
     * so they cost nothing and degrade to a neutral default when a heading does
     * not match.
     */
    $sections = array_values($sections);

    $iconFor = function (string $heading): string {
        $h = mb_strtolower($heading);

        return match (true) {
            str_contains($h, 'key concept'), str_contains($h, 'overview'), str_contains($h, 'introduction') => 'sparkles',
            str_contains($h, 'term'), str_contains($h, 'definition'), str_contains($h, 'glossary') => 'book',
            str_contains($h, 'misconception'), str_contains($h, 'mistake'), str_contains($h, 'common error'), str_contains($h, 'pitfall') => 'alert-circle',
            str_contains($h, 'example'), str_contains($h, 'application'), str_contains($h, 'worked') => 'info',
            str_contains($h, 'exam'), str_contains($h, 'tip'), str_contains($h, 'revision'), str_contains($h, 'practice') => 'pencil',
            str_contains($h, 'summary'), str_contains($h, 'recap'), str_contains($h, 'quick') => 'activity',
            str_contains($h, 'connection'), str_contains($h, 'relationship'), str_contains($h, 'link') => 'link',
            str_contains($h, 'formula'), str_contains($h, 'equation'), str_contains($h, 'method') => 'clipboard',
            default => 'document',
        };
    };

    // Plain text per section, for the client-side search filter.
    $searchIndex = array_map(
        static fn ($s) => mb_strtolower($s['heading'].' '.$s['body']),
        $sections
    );

    $key = $storageKey ?? 'guide:'.($guide->id ?? 'unknown');
@endphp

<div x-data="studyGuide({
        total: {{ count($sections) }},
        storageKey: @js($key),
        index: @js($searchIndex),
     })"
     x-init="restore()">

    {{-- ── Header: title, progress summary, controls ── --}}
    <div class="surface p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex min-w-0 items-center gap-4">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-surface-sunk text-muted">
                    <x-icon name="book" />
                </span>
                <div class="min-w-0">
                    <h3 class="font-display text-lg text-ink">{{ $guide->displayTitle() }}</h3>
                    <p class="text-xs text-faint">
                        {{ count($sections) }} {{ Str::plural('section', count($sections)) }}
                        <span x-show="done.size > 0" x-cloak>
                            · <span class="tnum" x-text="done.size"></span> completed
                        </span>
                        @if ($subtitle)
                            · {{ $subtitle }}
                        @endif
                    </p>
                </div>
            </div>

            @if (count($sections) > 1)
                <button type="button" class="btn btn-outline btn-sm" @click="toggleAll()">
                    <span x-text="open.size === total ? 'Collapse all' : 'Expand all'">Expand all</span>
                </button>
            @endif
        </div>

        {{-- Reading progress. Hidden until something is marked, so an untouched
             guide does not open with an empty progress bar. --}}
        @if (count($sections) > 0)
            <div class="mt-4" x-show="done.size > 0" x-cloak>
                <div class="flex items-center justify-between text-xs text-faint">
                    <span>Reading progress</span>
                    <span class="tnum" x-text="percent() + '%'"></span>
                </div>
                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-surface-sunk">
                    <div class="h-full rounded-full bg-success transition-all duration-300"
                         :style="`width: ${percent()}%`"></div>
                </div>
            </div>
        @endif
    </div>

    @if ($guide->summary)
        <div class="surface mt-4 border-s-2 border-s-accent p-5">
            <div class="flex items-center gap-2">
                <x-icon name="info" class="text-accent" />
                <h4 class="text-sm font-semibold text-ink">Overview</h4>
            </div>
            <x-prose :text="$guide->summary" class="mt-2" />
        </div>
    @endif

    {{-- ── Search ── --}}
    @if (count($sections) > 2)
        <div class="relative mt-4">
            <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-faint">
                <x-icon name="search" />
            </span>
            <input type="search" class="input ps-9" placeholder="Search this guide…"
                   x-model="query" @input="onSearch()" aria-label="Search the study guide">
            <button type="button" class="absolute inset-y-0 end-0 flex items-center pe-3 text-faint hover:text-ink"
                    x-show="query" x-cloak @click="query = ''; onSearch()" aria-label="Clear search">
                <x-icon name="x" />
            </button>
        </div>
    @endif

    <div class="mt-4 gap-5 lg:flex">

        {{-- ── Section navigator (wide screens only) ── --}}
        @if (count($sections) > 2)
            <nav class="hidden w-52 shrink-0 lg:block" aria-label="Sections">
                <div class="sticky top-4">
                    <p class="px-2 text-xs font-medium uppercase tracking-wider text-faint">Sections</p>
                    <ul class="mt-2 space-y-0.5">
                        @foreach ($sections as $i => $section)
                            <li x-show="visible({{ $i }})">
                                <button type="button"
                                        class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-start text-xs transition-colors"
                                        :class="active === {{ $i }}
                                            ? 'bg-brand-50 text-accent font-medium'
                                            : 'text-muted hover:bg-surface-sunk hover:text-ink'"
                                        @click="goTo({{ $i }})">
                                    <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full border transition-colors"
                                          :class="done.has({{ $i }})
                                              ? 'border-success bg-success text-white'
                                              : 'border-line-strong'">
                                        <template x-if="done.has({{ $i }})">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4"
                                                 class="h-2.5 w-2.5" aria-hidden="true">
                                                <path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </template>
                                    </span>
                                    <span class="truncate">{{ $section['heading'] }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </nav>
        @endif

        {{-- ── Sections ── --}}
        <div class="min-w-0 flex-1 space-y-3">
            @foreach ($sections as $i => $section)
                <section id="guide-section-{{ $i }}"
                         x-show="visible({{ $i }})"
                         class="overflow-hidden rounded-lg border transition-colors"
                         :class="done.has({{ $i }})
                             ? 'border-success/30 bg-success/5'
                             : (active === {{ $i }} ? 'border-accent/40' : 'border-line')">

                    <h4>
                        <button type="button"
                                class="flex w-full items-center justify-between gap-3 p-4 text-start"
                                :aria-expanded="open.has({{ $i }}) ? 'true' : 'false'"
                                aria-controls="guide-body-{{ $i }}"
                                @click="toggle({{ $i }})">
                            <span class="flex min-w-0 items-center gap-3">
                                <span class="shrink-0 text-muted"><x-icon :name="$iconFor($section['heading'])" /></span>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-semibold text-ink"
                                          :class="done.has({{ $i }}) && 'text-muted'">
                                        {{ $section['heading'] }}
                                    </span>
                                    <span class="mt-0.5 block truncate text-xs text-faint"
                                          x-show="! open.has({{ $i }})" x-cloak>
                                        {{ Str::limit(strip_tags($section['body']), 90) }}
                                    </span>
                                </span>
                            </span>

                            <span class="flex shrink-0 items-center gap-2">
                                <span class="hidden text-xs font-medium text-success sm:block"
                                      x-show="done.has({{ $i }})" x-cloak>Done</span>
                                <span class="text-faint transition-transform duration-200"
                                      :class="open.has({{ $i }}) && 'rotate-90'">
                                    <x-icon name="chevron-right" />
                                </span>
                            </span>
                        </button>
                    </h4>

                    {{-- x-show rather than x-collapse: the collapse plugin is not
                         bundled, and adding a dependency for a height animation
                         is not worth it. --}}
                    <div id="guide-body-{{ $i }}" x-show="open.has({{ $i }})" x-cloak
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0">
                        <div class="border-t border-line px-4 pb-4 pt-3">
                            <x-prose :text="$section['body']" />

                            <div class="mt-4 flex items-center justify-between gap-3">
                                <button type="button"
                                        class="inline-flex items-center gap-1.5 rounded-md border px-3 py-1.5 text-xs transition-colors"
                                        :class="done.has({{ $i }})
                                            ? 'border-success/50 bg-success/10 text-success'
                                            : 'border-line text-muted hover:border-accent/40 hover:text-accent'"
                                        @click="markDone({{ $i }})">
                                    <x-icon name="check" />
                                    <span x-text="done.has({{ $i }}) ? 'Mark not done' : 'Mark as done'">Mark as done</span>
                                </button>

                                @if ($i < count($sections) - 1)
                                    <button type="button" class="inline-flex items-center gap-1 text-xs text-accent hover:underline"
                                            @click="completeAndAdvance({{ $i }})">
                                        Next section <x-icon name="chevron-right" />
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </section>
            @endforeach

            {{-- No search results --}}
            <div class="py-12 text-center" x-show="query && matches() === 0" x-cloak>
                <p class="text-sm text-muted">No sections match “<span x-text="query"></span>”.</p>
                <button type="button" class="mt-2 text-sm text-accent hover:underline"
                        @click="query = ''; onSearch()">Clear search</button>
            </div>

            {{-- Finished --}}
            @if (count($sections) > 0)
                <div class="rounded-lg border border-success/30 bg-success/5 p-6 text-center"
                     x-show="done.size === total && total > 0" x-cloak>
                    <p class="font-medium text-success">Study guide complete</p>
                    <p class="mt-1 text-sm text-muted">
                        You've worked through all {{ count($sections) }} {{ Str::plural('section', count($sections)) }}.
                    </p>
                    <button type="button" class="btn btn-outline btn-sm mt-3" @click="reset()">
                        Reset progress
                    </button>
                </div>
            @endif

            @if ($keyTerms)
                <section class="surface p-5">
                    <div class="flex items-center gap-2">
                        <x-icon name="book" class="text-muted" />
                        <h4 class="text-sm font-semibold text-ink">Key terms</h4>
                    </div>
                    <dl class="mt-3 grid gap-2 sm:grid-cols-2">
                        @foreach ($keyTerms as $term)
                            <div class="rounded-md bg-surface-sunk p-3">
                                <dt class="text-sm font-medium text-ink">{{ $term['term'] }}</dt>
                                <dd class="mt-0.5 text-xs leading-relaxed text-muted">{{ $term['definition'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endif
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            function studyGuide({ total, storageKey, index }) {
                return {
                    total,
                    storageKey,
                    index,
                    // Sets, not arrays: membership is the only question asked.
                    open: new Set(total > 0 ? [0] : []),
                    done: new Set(),
                    active: 0,
                    query: '',
                    hidden: new Set(),

                    // Progress is a private reading aid, so it lives in the
                    // browser rather than costing a write per click.
                    restore() {
                        try {
                            const saved = JSON.parse(localStorage.getItem(this.storageKey) || '[]');
                            if (Array.isArray(saved)) this.done = new Set(saved.filter(n => Number.isInteger(n)));
                        } catch (e) { /* corrupt or unavailable — start clean */ }
                    },
                    persist() {
                        try {
                            localStorage.setItem(this.storageKey, JSON.stringify([...this.done]));
                        } catch (e) { /* private mode / quota — progress is non-essential */ }
                    },

                    percent() {
                        return this.total === 0 ? 0 : Math.round((this.done.size / this.total) * 100);
                    },

                    toggle(i) {
                        this.active = i;
                        this.open.has(i) ? this.open.delete(i) : this.open.add(i);
                        this.open = new Set(this.open);
                    },
                    toggleAll() {
                        this.open = this.open.size === this.total
                            ? new Set()
                            : new Set(Array.from({ length: this.total }, (_, i) => i));
                    },
                    markDone(i) {
                        this.done.has(i) ? this.done.delete(i) : this.done.add(i);
                        this.done = new Set(this.done);
                        this.persist();
                    },
                    reset() {
                        this.done = new Set();
                        this.persist();
                    },

                    goTo(i) {
                        this.active = i;
                        this.open.add(i);
                        this.open = new Set(this.open);
                        this.$nextTick(() => {
                            document.getElementById(`guide-section-${i}`)
                                ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        });
                    },
                    completeAndAdvance(i) {
                        this.done.add(i);
                        this.done = new Set(this.done);
                        this.persist();
                        this.open.delete(i);
                        this.goTo(i + 1);
                    },

                    // Filtering is done here rather than by re-rendering, so
                    // open/done state survives a search.
                    onSearch() {
                        const q = this.query.trim().toLowerCase();
                        this.hidden = new Set(
                            q === '' ? [] : this.index.map((t, i) => (t.includes(q) ? -1 : i)).filter(i => i >= 0)
                        );
                    },
                    visible(i) { return ! this.hidden.has(i); },
                    matches() { return this.total - this.hidden.size; },
                };
            }
        </script>
    @endpush
@endonce
