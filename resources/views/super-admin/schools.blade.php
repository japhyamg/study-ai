<x-layouts.studyai title="Schools">
    <div class="surface">
        <div class="px-5 py-3 border-b flex items-center justify-between">
            <span class="font-semibold text-ink">Schools</span>
            <button onclick="document.getElementById('createForm').classList.toggle('hidden')" class="px-3 py-1 btn btn-primary text-sm">New School</button>
        </div>

        <form id="createForm" method="POST" action="{{ route('super-admin.schools.store') }}" class="hidden px-5 py-4 border-b bg-paper-sunk space-y-3">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div><label class="text-xs text-muted">Name</label><input name="name" class="w-full border rounded px-2 py-1" required></div>
                <div><label class="text-xs text-muted">Slug</label><input name="slug" class="w-full border rounded px-2 py-1" required></div>
                <div><label class="text-xs text-muted">Logo URL</label><input name="logo" class="w-full border rounded px-2 py-1"></div>
            </div>
            <button class="px-3 py-1 btn btn-primary text-sm">Create</button>
        </form>

        <form method="GET" class="px-5 py-3 border-b flex gap-2">
            <input name="search" value="{{ $search }}" placeholder="Search name or slug..." class="flex-1 border rounded px-2 py-1 text-sm">
            <button class="px-3 py-1 bg-paper-sunk rounded text-sm">Search</button>
        </form>

        <div class="table-wrap"><table class="w-full text-sm">
            <thead class="text-left text-muted border-b">
                <tr><th class="px-5 py-2">Name</th><th class="px-5 py-2">Slug</th><th class="px-5 py-2">Members</th><th class="px-5 py-2"></th></tr>
            </thead>
            <tbody>
                @forelse($schools as $school)
                    <tr class="border-b">
                        <td class="px-5 py-2">{{ $school->name }}</td>
                        <td class="px-5 py-2 text-muted">{{ $school->slug }}</td>
                        <td class="px-5 py-2">{{ $school->members_count }}</td>
                        <td class="px-5 py-2 text-right whitespace-nowrap">
                            <form method="POST" action="{{ route('super-admin.schools.update', $school) }}" class="inline">
                                @csrf @method('PUT')
                                <input name="name" value="{{ $school->name }}" class="border rounded px-1 py-0.5 text-xs w-32">
                                <input name="slug" value="{{ $school->slug }}" class="border rounded px-1 py-0.5 text-xs w-24">
                                <button class="text-xs text-accent">Save</button>
                            </form>
                            <form method="POST" action="{{ route('super-admin.schools.destroy', $school) }}" class="inline" onsubmit="return confirm('Delete school?')">
                                @csrf @method('DELETE')
                                <button class="text-xs text-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-4 text-faint">No schools found.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        <div class="px-5 py-3 border-t">{{ $schools->links() }}</div>
    </div>
</x-layouts.studyai>
