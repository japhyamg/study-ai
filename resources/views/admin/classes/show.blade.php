<x-layouts.studyai title="Class: {{ $class->name }}">
    <div class="mb-4 flex gap-2">
        <a href="{{ route('admin.classes.edit', $class) }}" class="px-3 py-1 btn btn-primary text-sm">Edit</a>
        <a href="{{ route('admin.classes.invite-codes', $class) }}" class="px-3 py-1 bg-paper-sunk rounded text-sm">Invite Codes</a>
        <form method="POST" action="{{ route('admin.classes.destroy', $class) }}" onsubmit="return confirm('Delete this class?')">
            @csrf @method('DELETE')
            <button class="px-3 py-1 bg-red-600 text-white rounded text-sm">Delete</button>
        </form>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="surface p-5 lg:col-span-2">
            <div class="font-semibold text-ink mb-2">Details</div>
            <div class="text-sm text-muted">{{ $class->description ?? 'No description.' }}</div>
            <div class="mt-3 text-xs text-muted">Subject: {{ $class->subject?->name ?? '—' }} · Term: {{ $class->term?->name ?? '—' }} · Teacher: {{ $class->teacher?->name ?? 'Unassigned' }}</div>

            <div class="mt-6 font-semibold text-ink">Assign Teacher</div>
            <form method="POST" action="{{ route('admin.classes.assign-teacher', $class) }}" class="mt-2 flex gap-2">
                @csrf @method('PUT')
                <select name="teacher_id" class="border rounded px-2 py-1 text-sm">
                    <option value="">—</option>
                    @foreach($class->school->members()->where('role','teacher')->with('user')->get() as $m)
                        <option value="{{ $m->user_id }}" {{ $class->teacher_id == $m->user_id ? 'selected' : '' }}>{{ $m->user?->name }}</option>
                    @endforeach
                </select>
                <button class="px-3 py-1 btn btn-primary text-sm">Assign</button>
            </form>
        </div>

        <div class="surface p-5">
            <div class="font-semibold text-ink mb-2">Students ({{ $class->enrollments->count() }})</div>
            <form method="POST" action="{{ route('admin.classes.enroll', $class) }}" class="mb-3 flex gap-2">
                @csrf
                <select name="user_id" class="flex-1 border rounded px-2 py-1 text-sm">
                    <option value="">Enroll a member...</option>
                    @foreach($class->school->members()->where('role','student')->with('user')->get() as $m)
                        <option value="{{ $m->user_id }}">{{ $m->user?->name }} ({{ $m->user?->email }})</option>
                    @endforeach
                </select>
                <button class="px-3 py-1 btn btn-primary text-sm">Add</button>
            </form>
            <ul class="text-sm divide-y">
                @forelse($class->enrollments as $e)
                    <li class="py-2 flex items-center justify-between">
                        <span>{{ $e->user?->name ?? 'Unknown' }}</span>
                        <form method="POST" action="{{ route('admin.classes.unenroll', [$class, $e->user_id]) }}" onsubmit="return confirm('Unenroll?')">
                            @csrf @method('DELETE')
                            <button class="text-xs text-danger">Remove</button>
                        </form>
                    </li>
                @empty
                    <li class="py-2 text-faint">No students enrolled.</li>
                @endforelse
            </ul>
        </div>
    </div>
</x-layouts.studyai>
