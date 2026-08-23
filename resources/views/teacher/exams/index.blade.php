@php
    $toneFor = fn (string $s) => match ($s) {
        'published' => 'success',
        'archived' => '',
        default => 'warn',
    };
@endphp

<x-layouts.studyai title="Exams">
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <p class="max-w-prose text-sm text-muted">
            Set a paper, draw questions from the subject bank, then publish it.
        </p>

        <x-ui.button :href="route('teacher.exams.create')" icon="plus">New exam</x-ui.button>
    </div>

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

    {{-- Status filter. Counts sit on the tabs so the numbers are visible
         without clicking through each one. --}}
    <div class="mb-5 flex flex-wrap gap-1 border-b border-line">
        @foreach ($tabs as $key => $tab)
            <a href="{{ route('teacher.exams.index', $key ? ['status' => $key] : []) }}"
               class="tab-btn {{ (string) $status === (string) $key ? 'active' : '' }}">
                {{ $tab['label'] }}
                <span class="tnum ms-1 text-xs text-faint">({{ $tab['count'] }})</span>
            </a>
        @endforeach
    </div>

    @if ($exams->isEmpty())
        <x-ui.empty icon="clipboard"
                    title="{{ $status ? 'Nothing here' : 'No exams yet' }}"
                    message="{{ $status
                        ? 'No exams with this status. Try another tab.'
                        : 'Create an exam, then fill it from the subject question bank or write questions yourself.' }}" />
    @else
        <div class="surface overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-surface-sunk text-left text-xs text-muted">
                    <tr>
                        <th class="px-4 py-2.5 font-medium">Exam</th>
                        <th class="px-4 py-2.5 font-medium">Class</th>
                        <th class="px-4 py-2.5 font-medium">Setup</th>
                        <th class="px-4 py-2.5 font-medium">Sat</th>
                        <th class="px-4 py-2.5 font-medium">Status</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-line">
                    @foreach ($exams as $e)
                        @php
                            $ready = $e->questions_count > 0;
                        @endphp

                        <tr class="transition-colors hover:bg-surface-sunk/50">
                            <td class="px-4 py-3">
                                <a href="{{ route('teacher.exams.show', $e) }}"
                                   class="font-medium text-ink hover:text-accent">{{ $e->title }}</a>

                                @if ($e->subject)
                                    <span class="mt-0.5 block text-xs text-faint">{{ $e->subject->name }}</span>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-muted">{{ $e->classArm?->fullName() ?: 'All classes' }}</td>

                            <td class="px-4 py-3 text-xs text-muted">
                                <span class="{{ $ready ? '' : 'text-warning' }}">
                                    <span class="tnum">{{ $e->questions_count }}</span>
                                    {{ Str::plural('question', $e->questions_count) }}
                                </span>
                                <span class="mt-0.5 block text-faint">
                                    {{ $e->duration ? $e->duration.' min' : 'Untimed' }}
                                    · pass <span class="tnum">{{ (int) $e->pass_mark }}</span>%
                                </span>
                            </td>

                            <td class="px-4 py-3">
                                @if ($e->attempts_count)
                                    <a href="{{ route('teacher.exams.analytics', $e) }}"
                                       class="text-xs text-accent">
                                        <span class="tnum">{{ $e->attempts_count }}</span>
                                        {{ Str::plural('student', $e->attempts_count) }}
                                    </a>
                                @else
                                    <span class="text-xs text-faint">—</span>
                                @endif
                            </td>

                            <td class="px-4 py-3">
                                <x-ui.badge :tone="$toneFor($e->status)">{{ ucfirst($e->status) }}</x-ui.badge>

                                @if (! $ready && $e->status === 'draft')
                                    <span class="mt-0.5 block text-xs text-faint">Needs questions</span>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('teacher.exams.show', $e) }}" class="text-xs text-accent">Open</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($exams->hasPages())
            <div class="mt-4">{{ $exams->links() }}</div>
        @endif
    @endif
</x-layouts.studyai>
