<x-layouts.studyai title="School: {{ $school->name }}">
    <div class="mb-4">
        <a href="{{ route('super-admin.dashboard') }}?tab=schools" class="text-xs text-accent">← Back to Schools</a>
        <h2 class="font-semibold text-ink mt-2">{{ $school->name }}</h2>
        <p class="text-sm text-muted">{{ $school->slug }}</p>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-paper border border-line text-center" style="border-radius:3px; padding:1.25rem">
            <div class="text-3xl font-bold text-ink">{{ $memberCount }}</div>
            <div class="text-xs text-muted mt-1">Members</div>
        </div>
        <div class="bg-paper border border-line text-center" style="border-radius:3px; padding:1.25rem">
            <div class="text-3xl font-bold text-ink">{{ $materialCount }}</div>
            <div class="text-xs text-muted mt-1">Materials</div>
        </div>
        <div class="bg-paper border border-line text-center" style="border-radius:3px; padding:1.25rem">
            <div class="text-3xl font-bold text-ink">{{ $examCount }}</div>
            <div class="text-xs text-muted mt-1">Exams</div>
        </div>
        <div class="bg-paper border border-line text-center" style="border-radius:3px; padding:1.25rem">
            <div class="text-3xl font-bold text-ink">{{ $flashcardCount }}</div>
            <div class="text-xs text-muted mt-1">Flashcards</div>
        </div>
    </div>

    <div class="surface">
        <div class="px-5 py-3 border-b font-semibold text-ink">Members</div>
        @if($school->members->isEmpty())
            <div class="px-5 py-4 text-faint">No members.</div>
        @else
            <table class="w-full text-sm">
                <thead class="text-left text-muted border-b">
                    <tr><th class="px-5 py-2">Name</th><th class="px-5 py-2">Email</th><th class="px-5 py-2">Role</th><th class="px-5 py-2"></th></tr>
                </thead>
                <tbody>
                    @foreach($school->members as $m)
                        <tr class="border-b">
                            <td class="px-5 py-2">{{ $m->user?->name ?? '—' }}</td>
                            <td class="px-5 py-2 text-muted">{{ $m->user?->email ?? '—' }}</td>
                            <td class="px-5 py-2">
                                <form method="POST" action="{{ route('super-admin.schools.members.role', [$school, $m]) }}" class="inline-flex items-center gap-1">
                                    @csrf @method('PUT')
                                    <select name="role" class="text-xs border rounded px-1 py-0.5" onchange="this.form.submit()">
                                        <option value="teacher" {{ $m->role === 'teacher' ? 'selected' : '' }}>Teacher</option>
                                        <option value="admin" {{ $m->role === 'admin' ? 'selected' : '' }}>Admin</option>
                                        <option value="student" {{ $m->role === 'student' ? 'selected' : '' }}>Student</option>
                                    </select>
                                </form>
                            </td>
                            <td class="px-5 py-2 text-right"><a href="{{ route('super-admin.schools.show', $school) }}" class="text-xs text-faint">↻</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-layouts.studyai>
