<x-layouts.studyai title="Academic structure" subtitle="Sessions, terms, levels and assessment components">

    @php $needsSetup = $summary['levels'] === 0 && $summary['subjects'] === 0; @endphp

    @if ($needsSetup)
        <x-ui.card class="mb-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="max-w-xl">
                    <h2 class="text-sm font-semibold text-ink">Set up your academic structure</h2>
                    <p class="mt-1 text-sm text-muted">
                        We can create a starting set of class levels, subjects, terms and assessment
                        components. Everything is editable afterwards.
                    </p>
                </div>
                <form method="POST" action="{{ route('admin.academic.bootstrap') }}" class="flex flex-wrap items-center gap-2">
                    @csrf
                    <select name="preset" class="select w-auto">
                        @foreach (array_keys(config('academic.presets', [])) as $preset)
                            <option value="{{ $preset }}" @selected($preset === config('academic.preset'))>
                                {{ ucfirst($preset) }} preset
                            </option>
                        @endforeach
                    </select>
                    <button class="btn btn-primary">Create structure</button>
                </form>
            </div>
        </x-ui.card>
    @endif

    {{-- Current position --}}
    <div class="mb-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat label="Current session" :value="$currentSession?->name ?? '—'"
                   :meta="$currentSession?->start_date?->format('M Y').' – '.$currentSession?->end_date?->format('M Y')"
                   icon="calendar" />
        <x-ui.stat label="Current term" :value="$currentTerm?->name ?? '—'"
                   :meta="$currentTerm?->end_date ? 'Ends '.$currentTerm->end_date->format('j M Y') : null"
                   icon="clipboard" />
        <x-ui.stat label="Class levels" :value="$summary['levels']"
                   :meta="$summary['arms'].' class'.($summary['arms'] === 1 ? '' : 'es')"
                   icon="layers" :href="route('admin.levels.index')" />
        <x-ui.stat label="Subjects" :value="$summary['subjects']"
                   :meta="$summary['assignments'].' teacher assignment'.($summary['assignments'] === 1 ? '' : 's')"
                   icon="book" :href="route('admin.subjects.index')" />
    </div>

    {{-- Things needing attention --}}
    @if (! $needsSetup && ($summary['unassigned'] > 0 || ! $summary['weights_balance']))
        <div class="mb-5 space-y-2">
            @if ($summary['unassigned'] > 0)
                <div class="alert alert-warn">
                    <x-icon name="alert-circle" class="mt-px flex-none" />
                    <span>
                        {{ $summary['unassigned'] }} subject–class pairing{{ $summary['unassigned'] === 1 ? ' has' : 's have' }}
                        no teacher assigned.
                        <a href="{{ route('admin.classes.index') }}" class="font-medium underline">Review classes</a>
                    </span>
                </div>
            @endif

            @if (! $summary['weights_balance'])
                <div class="alert alert-warn">
                    <x-icon name="alert-circle" class="mt-px flex-none" />
                    <span>
                        Assessment weights don't total 100%.
                        <a href="{{ route('admin.assessment-types.index') }}" class="font-medium underline">Adjust weights</a>
                    </span>
                </div>
            @endif
        </div>
    @endif

    {{-- Sessions --}}
    <x-ui.card title="Academic sessions" subtitle="An academic year. Terms belong to a session.">
        <x-slot:actions>
            <button class="btn btn-primary btn-sm" onclick="document.getElementById('new-session').classList.toggle('hidden')">
                <x-icon name="plus" /> New session
            </button>
        </x-slot:actions>

        <form id="new-session" method="POST" action="{{ route('admin.sessions.store') }}"
              class="{{ $errors->any() ? '' : 'hidden' }} mb-4 grid gap-3 rounded-lg border bg-surface-sunk p-3 sm:grid-cols-4">
            @csrf
            <x-ui.field label="Name" name="name">
                <input name="name" class="input" placeholder="2025/2026" value="{{ old('name') }}" required>
            </x-ui.field>
            <x-ui.field label="Starts" name="start_date">
                <input name="start_date" type="date" class="input" value="{{ old('start_date') }}">
            </x-ui.field>
            <x-ui.field label="Ends" name="end_date">
                <input name="end_date" type="date" class="input" value="{{ old('end_date') }}">
            </x-ui.field>
            <div class="flex items-end gap-2">
                <label class="mb-2 inline-flex items-center gap-2 text-sm text-muted">
                    <input type="checkbox" name="is_current" value="1" class="checkbox"> Make current
                </label>
                <button class="btn btn-primary btn-sm mb-1">Add</button>
            </div>
        </form>

        @forelse ($sessions as $session)
            <div class="flex flex-wrap items-center justify-between gap-3 border-b py-3 last:border-0">
                <div class="min-w-0">
                    <p class="flex flex-wrap items-center gap-2 text-sm font-medium text-ink">
                        {{ $session->name }}
                        @if ($session->is_current)<span class="badge badge-ok">Current</span>@endif
                    </p>
                    <p class="mt-0.5 text-xs text-faint">
                        {{ $session->terms_count }} term{{ $session->terms_count === 1 ? '' : 's' }}
                        @if ($session->start_date)
                            · {{ $session->start_date->format('M Y') }} – {{ $session->end_date?->format('M Y') ?? '…' }}
                        @endif
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @unless ($session->is_current)
                        <form method="POST" action="{{ route('admin.sessions.activate', $session) }}">
                            @csrf @method('put')
                            <button class="btn btn-outline btn-sm">Make current</button>
                        </form>
                        <form method="POST" action="{{ route('admin.sessions.destroy', $session) }}"
                              onsubmit="return confirm('Delete {{ $session->name }}? Its terms will be removed too.')">
                            @csrf @method('delete')
                            <button class="btn btn-danger-quiet btn-sm"><x-icon name="trash" /></button>
                        </form>
                    @endunless
                    <a href="{{ route('admin.terms.index') }}" class="btn btn-ghost btn-sm">Terms</a>
                </div>
            </div>
        @empty
            <x-ui.empty icon="calendar" title="No sessions yet"
                        message="Add an academic session to start organising terms." />
        @endforelse
    </x-ui.card>
</x-layouts.studyai>
