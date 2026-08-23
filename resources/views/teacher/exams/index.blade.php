<x-layouts.studyai title="Exams">
    <div class="surface">
        <div class="px-5 py-3 border-b flex items-center justify-between">
            <span class="font-semibold text-ink">Exams</span>
            <a href="{{ route('teacher.exams.create') }}" class="px-3 py-1 btn btn-primary text-sm">New Exam</a>
        </div>
        <form method="GET" class="px-5 py-3 border-b flex gap-2">
            <select name="status" class="border rounded px-2 py-1 text-sm">
                <option value="">All</option>
                <option value="draft" {{ $status==='draft'?'selected':'' }}>Draft</option>
                <option value="published" {{ $status==='published'?'selected':'' }}>Published</option>
                <option value="archived" {{ $status==='archived'?'selected':'' }}>Archived</option>
            </select>
            <button class="px-3 py-1 bg-paper-sunk rounded text-sm">Filter</button>
        </form>
        <table class="w-full text-sm">
            <thead class="text-left text-muted border-b">
                <tr><th class="px-5 py-2">Title</th><th class="px-5 py-2">Class</th><th class="px-5 py-2">Questions</th><th class="px-5 py-2">Status</th><th class="px-5 py-2"></th></tr>
            </thead>
            <tbody>
                @forelse($exams as $e)
                    <tr class="border-b">
                        <td class="px-5 py-2">{{ $e->title }}</td>
                        <td class="px-5 py-2 text-muted">{{ $e->classArm?->fullName() ?? '—' }}</td>
                        <td class="px-5 py-2">{{ $e->questions_count }}</td>
                        <td class="px-5 py-2">
                            <span class="px-2 py-0.5 rounded text-xs {{ $e->status==='published' ? 'bg-green-100 text-ok' : 'bg-paper-sunk text-muted' }}">{{ $e->status }}</span>
                        </td>
                        <td class="px-5 py-2 text-right">
                            <a href="{{ route('teacher.exams.show', $e) }}" class="text-xs text-accent">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-4 text-faint">No exams.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-5 py-3 border-t">{{ $exams->links() }}</div>
    </div>
</x-layouts.studyai>
