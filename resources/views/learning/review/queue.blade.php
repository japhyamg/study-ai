<x-layouts.studyai title="Review queue"
                   subtitle="Material teachers have submitted for approval.">

    <div class="mb-5 grid gap-3 sm:grid-cols-3">
        <x-ui.stat label="Awaiting review" :value="$counts['awaiting']" icon="clipboard"
                   :tone="$counts['awaiting'] > 0 ? 'warn' : null" />
        <x-ui.stat label="Changes requested" :value="$counts['changes']" icon="pencil" />
        <x-ui.stat label="Published" :value="$counts['published']" icon="check-circle" tone="ok" />
    </div>

    @if ($materials->isEmpty())
        <x-ui.empty icon="check-circle" title="Nothing waiting"
                    message="When a teacher submits material for review it appears here." />
    @else
        <div class="surface divide-y divide-line">
            @foreach ($materials as $material)
                <a href="{{ route('learning.materials.show', $material) }}"
                   class="flex items-start justify-between gap-4 px-5 py-4 transition-colors hover:bg-surface-raised">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="truncate font-medium text-ink">{{ $material->title }}</span>
                            <x-ui.badge :tone="$material->stateTone()">{{ $material->stateLabel() }}</x-ui.badge>
                        </div>

                        <div class="mt-1 text-xs text-faint">
                            {{ $material->creator?->name ?? 'Unknown teacher' }}
                            @if ($material->subject) · {{ $material->subject->name }} @endif
                            @if ($material->classArm) · {{ $material->classArm->fullName() }} @endif
                        </div>

                        <div class="mt-1.5 flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted">
                            <span class="tnum">{{ $material->flashcards_count }} flashcards</span>
                            <span class="tnum">{{ $material->questions_count }} questions</span>
                            @if ($material->submitted_at)
                                <span>submitted {{ $material->submitted_at->diffForHumans() }}</span>
                            @endif
                        </div>
                    </div>

                    <span class="mt-1 shrink-0 text-faint"><x-icon name="chevron-right" /></span>
                </a>
            @endforeach
        </div>

        @if ($materials->hasPages())
            <div class="mt-5">{{ $materials->links() }}</div>
        @endif
    @endif
</x-layouts.studyai>
