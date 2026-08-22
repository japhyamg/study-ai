<x-layouts.studyai title="Topics">
    <div class="flex items-center justify-between mb-4">
        <span class="font-semibold text-ink">My Study Topics</span>
    </div>

    @if(session('status'))<div class="text-ok text-sm mb-3">{{ session('status') }}</div>@endif
    @if(session('error'))<div class="text-danger text-sm mb-3">{{ session('error') }}</div>@endif

    <form method="POST" action="{{ route('student.topics.generate') }}" class="surface p-5 space-y-3 mb-4">
        @csrf
        <label class="text-sm font-medium">Generate study topics for a subject</label>
        <div class="flex gap-2">
            <input name="topic" required placeholder="e.g. Cell Biology, Quadratic Equations" class="flex-1 border rounded px-2 py-1 text-sm">
            <button class="btn btn-primary">Generate</button>
        </div>
    </form>

    <div class="surface">
        <ul class="divide-y text-sm">
            @forelse($topics as $t)
                <li class="px-5 py-3 flex items-start justify-between gap-3">
                    <div>
                        <div class="font-medium">{{ $t->name }}</div>
                        @if($t->content && is_string($t->content) && !str_starts_with($t->content,'{'))
                            <div class="text-xs text-faint whitespace-pre-line">{{ $t->content }}</div>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('student.topics.destroy', $t) }}">@csrf @method('DELETE')<button class="text-danger text-xs">Delete</button></form>
                </li>
            @empty
                <li class="px-5 py-4 text-faint">No topics yet. Generate some above.</li>
            @endforelse
        </ul>
    </div>
    {{ $topics->links() }}
</x-layouts.studyai>
