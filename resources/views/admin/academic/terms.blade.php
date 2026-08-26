<x-layouts.studyai title="Sessions & terms" subtitle="The academic calendar for your school">
    <x-slot:actions>
        <a href="{{ route('admin.academic.index') }}" class="btn btn-outline btn-sm">Academic overview</a>
    </x-slot:actions>

    @forelse ($sessions as $session)
        <x-ui.card class="mb-5">
            <x-slot:title>
                {{ $session->name }}
                @if ($session->is_current)<span class="badge badge-ok ms-1">Current</span>@endif
            </x-slot:title>
            <x-slot:actions>
                <button class="btn btn-outline btn-sm"
                        onclick="document.getElementById('term-form-{{ $session->id }}').classList.toggle('hidden')">
                    <x-icon name="plus" /> Add term
                </button>
            </x-slot:actions>

            <form id="term-form-{{ $session->id }}" method="POST" action="{{ route('admin.terms.store') }}"
                  class="hidden mb-4 grid gap-3 rounded-lg border bg-surface-sunk p-3 sm:grid-cols-5">
                @csrf
                <input type="hidden" name="academic_session_id" value="{{ $session->id }}">
                <x-ui.field label="Name" name="name">
                    <input name="name" class="input" placeholder="First Term" required>
                </x-ui.field>
                <x-ui.field label="Starts" name="start_date">
                    <input name="start_date" type="date" class="input">
                </x-ui.field>
                <x-ui.field label="Ends" name="end_date">
                    <input name="end_date" type="date" class="input">
                </x-ui.field>
                <x-ui.field label="Next resumption" name="resumption_date">
                    <input name="resumption_date" type="date" class="input">
                </x-ui.field>
                <div class="flex items-end">
                    <button class="btn btn-primary btn-sm mb-1">Add term</button>
                </div>
            </form>

            @if ($session->terms->isEmpty())
                <x-ui.empty message="No terms in this session yet." />
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Term</th>
                                <th>Starts</th>
                                <th>Ends</th>
                                <th>Resumption</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($session->terms as $term)
                                <tr>
                                    <td class="tnum text-faint">{{ $term->sequence }}</td>
                                    <td>
                                        <span class="font-medium text-ink">{{ $term->name }}</span>
                                        @if ($term->is_current)<span class="badge badge-ok ms-1.5">Current</span>@endif
                                    </td>
                                    <td class="text-muted">{{ $term->start_date?->format('j M Y') ?? '—' }}</td>
                                    <td class="text-muted">{{ $term->end_date?->format('j M Y') ?? '—' }}</td>
                                    <td class="text-muted">{{ $term->resumption_date?->format('j M Y') ?? '—' }}</td>
                                    <td>
                                        <div class="flex items-center justify-end gap-1.5">
                                            @unless ($term->is_current)
                                                <form method="POST" action="{{ route('admin.terms.activate', $term) }}">
                                                    @csrf @method('put')
                                                    <button class="btn btn-outline btn-sm">Make current</button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.terms.destroy', $term) }}"
                                                      onsubmit="return confirm('Delete {{ $term->name }}?')">
                                                    @csrf @method('delete')
                                                    <button class="btn btn-danger-quiet btn-sm"><x-icon name="trash" /></button>
                                                </form>
                                            @endunless
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>
    @empty
        <x-ui.empty icon="calendar" title="No academic sessions"
                    message="Create a session on the academic overview first.">
            <x-slot:action>
                <a href="{{ route('admin.academic.index') }}" class="btn btn-primary btn-sm">Go to overview</a>
            </x-slot:action>
        </x-ui.empty>
    @endforelse
</x-layouts.studyai>
