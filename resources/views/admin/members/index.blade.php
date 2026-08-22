<x-layouts.studyai title="Members">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="surface p-5 lg:col-span-1">
            <div class="font-semibold text-ink mb-3">Invite Member</div>
            <form method="POST" action="{{ route('admin.members.invite') }}" class="space-y-3">
                @csrf
                <input name="name" placeholder="Name" class="w-full border rounded px-2 py-1 text-sm">
                <input name="email" placeholder="Email" type="email" class="w-full border rounded px-2 py-1 text-sm" required>
                <select name="role" class="w-full border rounded px-2 py-1 text-sm">
                    <option value="student">Student</option>
                    <option value="teacher">Teacher</option>
                    <option value="admin">Admin</option>
                </select>
                <button class="px-3 py-1 btn btn-primary text-sm">Invite</button>
            </form>
            <div class="font-semibold text-ink mt-6 mb-3">Bulk Invite</div>
            <form method="POST" action="{{ route('admin.members.bulk-invite') }}" class="space-y-3">
                @csrf
                <textarea name="emails" placeholder="one@email.com, two@email.com" class="w-full border rounded px-2 py-1 text-sm" rows="4" required></textarea>
                <select name="role" class="w-full border rounded px-2 py-1 text-sm">
                    <option value="student">Student</option>
                    <option value="teacher">Teacher</option>
                    <option value="admin">Admin</option>
                </select>
                <button class="px-3 py-1 btn btn-primary text-sm">Invite All</button>
            </form>
        </div>

        <div class="surface p-5 lg:col-span-2">
            <form method="GET" class="mb-3 flex gap-2">
                <input name="search" value="{{ $search }}" placeholder="Search name/email..." class="flex-1 border rounded px-2 py-1 text-sm">
                <button class="px-3 py-1 bg-paper-sunk rounded text-sm">Search</button>
            </form>
            <table class="w-full text-sm">
                <thead class="text-left text-muted border-b">
                    <tr><th class="px-3 py-2">Name</th><th class="px-3 py-2">Email</th><th class="px-3 py-2">Role</th><th class="px-3 py-2"></th></tr>
                </thead>
                <tbody>
                    @forelse($members as $m)
                        <tr class="border-b">
                            <td class="px-3 py-2">{{ $m->user?->name ?? 'Unknown' }}</td>
                            <td class="px-3 py-2 text-muted">{{ $m->user?->email }}</td>
                            <td class="px-3 py-2">
                                <form method="POST" action="{{ route('admin.members.role', $m) }}" class="inline">
                                    @csrf @method('PUT')
                                    <select name="role" onchange="this.form.submit()" class="border rounded px-1 py-0.5 text-xs">
                                        <option value="student" {{ $m->role=='student'?'selected':'' }}>student</option>
                                        <option value="teacher" {{ $m->role=='teacher'?'selected':'' }}>teacher</option>
                                        <option value="admin" {{ $m->role=='admin'?'selected':'' }}>admin</option>
                                    </select>
                                </form>
                            </td>
                            <td class="px-3 py-2 text-right">
                                <form method="POST" action="{{ route('admin.members.remove', $m) }}" onsubmit="return confirm('Remove member?')">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-danger">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-3 py-4 text-faint">No members found.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="px-3 py-3 border-t">{{ $members->links() }}</div>
        </div>
    </div>
</x-layouts.studyai>
