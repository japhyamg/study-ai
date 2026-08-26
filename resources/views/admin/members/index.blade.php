<x-layouts.studyai title="Members">
    {{-- Role filter tabs --}}
    <div class="flex flex-wrap items-center gap-2 mb-5">
        <a href="{{ route('admin.members', array_filter(['search' => $search])) }}" class="chip {{ $type === null ? 'chip-active' : '' }}">All · {{ $counts['all'] }}</a>
        <a href="{{ route('admin.members', array_filter(['type' => 'admin', 'search' => $search])) }}" class="chip {{ $type === 'admin' ? 'chip-active' : '' }}">Admins · {{ $counts['admin'] }}</a>
        <a href="{{ route('admin.members', array_filter(['type' => 'teacher', 'search' => $search])) }}" class="chip {{ $type === 'teacher' ? 'chip-active' : '' }}">Teachers · {{ $counts['teacher'] }}</a>
        <a href="{{ route('admin.members', array_filter(['type' => 'student', 'search' => $search])) }}" class="chip {{ $type === 'student' ? 'chip-active' : '' }}">Students · {{ $counts['student'] }}</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Invite panel --}}
        <div class="surface p-5 lg:col-span-1 h-fit">
            <div class="font-semibold text-ink mb-1">Invite members</div>
            <p class="text-sm faint mb-3">Accounts are created automatically and assigned to their role table.</p>

            <form method="POST" action="{{ route('admin.members.invite') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="field-label" for="invite_name">Name</label>
                    <input id="invite_name" name="name" placeholder="Optional" class="input">
                </div>
                <div>
                    <label class="field-label" for="invite_email">Email</label>
                    <input id="invite_email" name="email" type="email" placeholder="person@school.com" class="input" required>
                </div>
                <div>
                    <label class="field-label" for="invite_role">Role</label>
                    <select id="invite_role" name="role" class="select">
                        <option value="student">Student</option>
                        <option value="teacher">Teacher</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
                <button class="btn btn-primary btn-block">Send invite</button>
            </form>

            <div class="font-semibold text-ink mt-6 mb-3">Bulk invite</div>
            <form method="POST" action="{{ route('admin.members.bulk-invite') }}" class="space-y-3">
                @csrf
                <textarea name="emails" placeholder="one@school.com, two@school.com" class="textarea" rows="4" required></textarea>
                <select name="role" class="select">
                    <option value="student">Students</option>
                    <option value="teacher">Teachers</option>
                    <option value="admin">Administrators</option>
                </select>
                <button class="btn btn-outline btn-block">Invite all</button>
            </form>
        </div>

        {{-- Members table --}}
        <div class="surface lg:col-span-2">
            <form method="GET" class="p-4 flex gap-2" style="border-bottom:1px solid var(--line)">
                @if($type)<input type="hidden" name="type" value="{{ $type }}">@endif
                <input name="search" value="{{ $search }}" placeholder="Search name or email…" class="flex-1 input">
                <button class="btn btn-outline">Search</button>
            </form>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Member</th><th>Role</th><th class="text-right">Actions</th></tr>
                    </thead>
                    <tbody>
                        @forelse($members as $m)
                            <tr>
                                <td class="px-3 sm:px-4 py-3">
                                    <div class="flex items-center gap-2.5">
                                        <x-ui.avatar :name="$m['user']?->name ?? '?'"/>
                                        <div class="min-w-0">
                                            <div class="font-medium truncate">{{ $m['user']?->name ?? 'Unknown' }}</div>
                                            <div class="text-xs faint truncate">{{ $m['user']?->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 sm:px-4">
                                    <span class="badge {{ $m['type'] === 'admin' ? 'badge-accent' : ($m['type'] === 'teacher' ? 'badge-warn' : '') }}">{{ \App\Support\Members\MemberTypes::label($m['type']) }}</span>
                                </td>
                                <td class="px-3 sm:px-4 text-right whitespace-nowrap">
                                    <form method="POST" action="{{ route('admin.members.role', [$m['type'], $m['id']]) }}" class="inline">
                                        @csrf @method('PUT')
                                        <select name="role" onchange="this.form.submit()" class="select !w-auto !py-1 !px-2 text-xs" aria-label="Change role">
                                            <option value="student" {{ $m['type'] == 'student' ? 'selected' : '' }}>as Student</option>
                                            <option value="teacher" {{ $m['type'] == 'teacher' ? 'selected' : '' }}>as Teacher</option>
                                            <option value="admin" {{ $m['type'] == 'admin' ? 'selected' : '' }}>as Admin</option>
                                        </select>
                                    </form>
                                    <form method="POST" action="{{ route('admin.members.remove', [$m['type'], $m['id']]) }}" class="inline" onsubmit="return confirm('Remove this member?')">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-danger hover:underline ml-2">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-8 text-center faint">No members found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-3" style="border-top:1px solid var(--line)">
                {{ $members->appends(array_filter(['type' => $type, 'search' => $search]))->links() }}
            </div>
        </div>
    </div>
</x-layouts.studyai>
