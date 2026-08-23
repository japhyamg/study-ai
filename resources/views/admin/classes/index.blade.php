<x-layouts.studyai title="Classes" subtitle="Groups students belong to">
    <x-slot:actions>
        <a href="{{ route('admin.classes.create') }}" class="btn btn-primary btn-sm">
            <x-icon name="plus" /> New class
        </a>
    </x-slot:actions>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-2">
        <x-ui.field label="Search" name="q" class="w-full sm:w-56">
            <input name="q" class="input" placeholder="Class name" value="{{ request('q') }}">
        </x-ui.field>
        <x-ui.field label="Level" name="level" class="w-full sm:w-48">
            <select name="level" class="select">
                <option value="">All levels</option>
                @foreach ($levels as $level)
                    <option value="{{ $level->id }}" @selected(request('level') === $level->id)>{{ $level->name }}</option>
                @endforeach
            </select>
        </x-ui.field>
        <button class="btn btn-outline mb-0.5">Filter</button>
        @if (request()->hasAny(['q', 'level']))
            <a href="{{ route('admin.classes.index') }}" class="btn btn-ghost mb-0.5">Clear</a>
        @endif
    </form>

    @if ($arms->isEmpty())
        <x-ui.empty icon="users" title="No classes yet"
                    message="Create a class so students have somewhere to belong.">
            <x-slot:action>
                <a href="{{ route('admin.classes.create') }}" class="btn btn-primary btn-sm">New class</a>
            </x-slot:action>
        </x-ui.empty>
    @else
        <div class="surface">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Class</th><th>Stream</th><th>Form teacher</th>
                            <th class="num">Students</th><th class="num">Subjects</th>
                            <th>Invite code</th><th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($arms as $arm)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.classes.show', $arm) }}" class="font-medium text-ink hover:text-accent">
                                        {{ $arm->fullName() }}
                                    </a>
                                </td>
                                <td class="text-muted">{{ $arm->stream ?? '—' }}</td>
                                <td class="text-muted">{{ $arm->formTeacher?->name ?? '—' }}</td>
                                <td class="num tnum">
                                    {{ $arm->enrollments_count }}
                                    <span class="text-faint">/ {{ $arm->capacity }}</span>
                                </td>
                                <td class="num tnum">{{ $arm->subject_assignments_count }}</td>
                                <td><code class="text-xs text-muted">{{ $arm->invite_code }}</code></td>
                                <td>
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a href="{{ route('admin.classes.edit', $arm) }}" class="btn btn-ghost btn-sm"><x-icon name="pencil" /></a>
                                        <a href="{{ route('admin.classes.show', $arm) }}" class="btn btn-outline btn-sm">Manage</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-layouts.studyai>
