@php use App\Models\Material; @endphp

<x-layouts.studyai title="Study Guides"
                   subtitle="Review study content submitted by teachers. Approve, reject, or request changes.">

    @if (session('status'))
        <div class="mb-4 rounded-md border border-success/30 bg-success/5 px-4 py-2.5 text-sm text-success">
            {{ session('status') }}
        </div>
    @endif

    {{-- Status filter --}}
    <div class="mb-5 flex flex-wrap gap-1 border-b border-line">
        @foreach ($filters as $key => $filter)
            <a href="{{ route('learning.review', ['status' => $key]) }}"
               class="tab-btn {{ $status === $key ? 'active' : '' }}">
                {{ $filter['label'] }}
                <span class="tnum ms-1 text-xs text-faint">({{ $filter['count'] }})</span>
            </a>
        @endforeach
    </div>

    @if ($materials->isEmpty())
        <x-ui.empty icon="document"
                    :title="'Nothing '.strtolower($filters[$status]['label'])"
                    :message="$status === 'pending'
                        ? 'When a teacher submits material for review it appears here.'
                        : 'No study guides in this state.'" />
    @else
        <div class="surface overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b border-line text-left text-muted">
                    <tr>
                        <th class="px-4 py-2.5 font-medium">Title</th>
                        <th class="px-4 py-2.5 font-medium">Teacher</th>
                        <th class="px-4 py-2.5 font-medium">Subject</th>
                        <th class="px-4 py-2.5 font-medium">Class</th>
                        <th class="px-4 py-2.5 font-medium">Content</th>
                        <th class="px-4 py-2.5 font-medium">Status</th>
                        <th class="px-4 py-2.5 font-medium">Submitted</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($materials as $material)
                        <tr class="transition-colors hover:bg-surface-sunk">
                            <td class="px-4 py-3 font-medium text-ink">{{ $material->title }}</td>
                            <td class="px-4 py-3 text-muted">{{ $material->creator?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-muted">{{ $material->subject?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-muted">{{ $material->classArm?->fullName() ?? 'All classes' }}</td>

                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    @if ($material->study_guide_exists)
                                        <span class="badge">Guide</span>
                                    @endif
                                    @if ($material->flashcards_count)
                                        <span class="badge">{{ $material->flashcards_count }} cards</span>
                                    @endif
                                    @if ($material->questions_count)
                                        <span class="badge badge-warn">{{ $material->questions_count }} Qs</span>
                                    @endif
                                    @if (! $material->study_guide_exists && ! $material->flashcards_count && ! $material->questions_count)
                                        <span class="text-xs text-faint">—</span>
                                    @endif
                                </div>
                            </td>

                            <td class="px-4 py-3">
                                <x-ui.badge :tone="$material->stateTone()">{{ $material->stateLabel() }}</x-ui.badge>
                            </td>

                            <td class="px-4 py-3 text-xs text-faint">
                                {{ ($material->submitted_at ?? $material->created_at)?->diffForHumans() }}
                            </td>

                            <td class="px-4 py-3 text-end">
                                <a href="{{ route('learning.materials.show', $material) }}"
                                   class="whitespace-nowrap text-sm text-accent">Review →</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($materials->hasPages())
            <div class="mt-5">{{ $materials->withQueryString()->links() }}</div>
        @endif
    @endif
</x-layouts.studyai>
