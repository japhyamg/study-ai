<x-layouts.studyai title="Classes">
    <div class="surface">
        <div class="px-5 py-3 border-b flex items-center justify-between">
            <span class="font-semibold text-ink">Classes</span>
            <a href="{{ route('admin.classes.create') }}" class="px-3 py-1 btn btn-primary text-sm">New Class</a>
        </div>
        <table class="w-full text-sm">
            <thead class="text-left text-muted border-b">
                <tr><th class="px-5 py-2">Name</th><th class="px-5 py-2">Subject</th><th class="px-5 py-2">Teacher</th><th class="px-5 py-2">Term</th><th class="px-5 py-2"></th></tr>
            </thead>
            <tbody>
                @forelse($classes as $c)
                    <tr class="border-b">
                        <td class="px-5 py-2">{{ $c->name }}</td>
                        <td class="px-5 py-2 text-muted">{{ $c->subject?->name ?? '—' }}</td>
                        <td class="px-5 py-2">{{ $c->teacher?->name ?? 'Unassigned' }}</td>
                        <td class="px-5 py-2 text-muted">{{ $c->term?->name ?? '—' }}</td>
                        <td class="px-5 py-2 text-right">
                            <a href="{{ route('admin.classes.show', $c) }}" class="text-xs text-accent">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-4 text-faint">No classes yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-5 py-3 border-t">{{ $classes->links() }}</div>
    </div>
</x-layouts.studyai>
