<x-layouts.studyai title="School: {{ $school->name }}">
    <div class="mb-5">
        <a href="{{ route('super-admin.dashboard') }}?tab=schools" class="text-xs text-accent">← Back to Schools</a>
        <div class="flex items-center gap-3 mt-2 flex-wrap">
            <h2 class="text-xl font-semibold text-ink">{{ $school->name }}</h2>
            @if($school->appUrl())
                <span class="badge">{{ $school->slug }} · subdomain workspace</span>
            @else
                <span class="badge">{{ $school->slug }}</span>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="stat text-center">
            <div class="stat-value">{{ $memberCount }}</div>
            <div class="stat-label mt-1">Members</div>
        </div>
        <div class="stat text-center">
            <div class="stat-value">{{ $materialCount }}</div>
            <div class="stat-label mt-1">Materials</div>
        </div>
        <div class="stat text-center">
            <div class="stat-value">{{ $examCount }}</div>
            <div class="stat-label mt-1">Exams</div>
        </div>
        <div class="stat text-center">
            <div class="stat-value">{{ $flashcardCount }}</div>
            <div class="stat-label mt-1">Flashcards</div>
        </div>
    </div>

    <div class="surface">
        <div class="px-5 py-3 border-b font-semibold text-ink">Members</div>
        @if($members->isEmpty())
            <div class="px-5 py-6 text-faint">No members yet.</div>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Member</th><th>Type</th><th>Joined</th><th>Change role</th></tr>
                    </thead>
                    <tbody>
                        @foreach($members as $m)
                            <tr class="border-b">
                                <td class="px-4 py-2.5">
                                    <div class="font-medium">{{ $m['user']?->name ?? '—' }}</div>
                                    <div class="text-xs faint">{{ $m['user']?->email ?? '' }}</div>
                                </td>
                                <td class="px-4">
                                    <span class="badge {{ $m['type'] === 'admin' ? 'badge-accent' : ($m['type'] === 'teacher' ? 'badge-warn' : '') }}">{{ \App\Support\Members\MemberTypes::label($m['type']) }}</span>
                                </td>
                                <td class="px-4 text-muted text-xs">{{ $m['created_at']?->format('M j, Y') }}</td>
                                <td class="px-4">
                                    <form method="POST" action="{{ route('super-admin.schools.members.role', [$school, $m['type'], $m['id']]) }}" class="inline-flex items-center gap-1">
                                        @csrf @method('PUT')
                                        <select name="role" class="select !w-auto !py-1 !px-2 text-xs" onchange="this.form.submit()">
                                            <option value="student" {{ $m['type'] === 'student' ? 'selected' : '' }}>Student</option>
                                            <option value="teacher" {{ $m['type'] === 'teacher' ? 'selected' : '' }}>Teacher</option>
                                            <option value="admin" {{ $m['type'] === 'admin' ? 'selected' : '' }}>Admin</option>
                                        </select>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts.studyai>
