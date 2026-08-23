<x-layouts.studyai title="Materials">
    <div class="flex items-center justify-between mb-4">
        <span class="font-semibold text-ink">Materials</span>
        <a href="{{ route('teacher.materials.create') }}" class="px-3 py-1 btn btn-primary text-sm">Upload New</a>
    </div>

    @if(session('status'))<div class="text-ok text-sm mb-3">{{ session('status') }}</div>@endif

    <div class="surface overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-paper-sunk text-left text-muted">
                <tr>
                    <th class="px-4 py-2">Title</th>
                    <th class="px-4 py-2">Type</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2">Published</th>
                    <th class="px-4 py-2">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($materials as $m)
                    <tr>
                        <td class="px-4 py-2">{{ $m->title }}</td>
                        <td class="px-4 py-2 uppercase text-xs text-faint">{{ $m->type }}</td>
                        <td class="px-4 py-2">
                            <x-ui.badge :tone="$m->stateTone()">{{ $m->stateLabel() }}</x-ui.badge>
                        </td>
                        <td class="px-4 py-2 text-xs text-faint">
                            {{ $m->published_at?->diffForHumans() ?? '—' }}
                        </td>
                        <td class="px-4 py-2 flex gap-2">
                            <a href="{{ route('learning.materials.show', $m) }}" class="text-accent text-xs">Open</a>
                            <a href="{{ route('teacher.materials.edit', $m) }}" class="text-muted text-xs">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-faint">No materials yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $materials->links() }}
</x-layouts.studyai>
