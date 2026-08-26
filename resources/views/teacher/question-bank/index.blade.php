<x-layouts.studyai title="Question Bank">
    <div class="flex items-center justify-between mb-4">
        <span class="font-semibold text-ink">Question Bank</span>
        <span class="text-xs text-faint">{{ $questions->total() }} questions</span>
    </div>

    @if(session('status'))<div class="text-ok text-sm mb-3">{{ session('status') }}</div>@endif

    <form method="POST" action="{{ route('teacher.question-bank.store') }}" class="surface p-5 space-y-3 mb-4">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="text-sm font-medium">Subject</label>
                <select name="subject_id" class="w-full border rounded px-2 py-1 text-sm">
                    <option value="">None</option>
                    @foreach($subjects as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="text-sm font-medium">Type</label>
                <select name="type" class="w-full border rounded px-2 py-1 text-sm">
                    <option value="mcq">Multiple Choice</option>
                    <option value="true_false">True / False</option>
                    <option value="fill_blank">Fill in the Blank</option>
                    <option value="short_answer">Short Answer</option>
                    <option value="essay">Essay</option>
                </select>
            </div>
        </div>
        <div>
            <label class="text-sm font-medium">Question</label>
            <input name="question" required class="w-full border rounded px-2 py-1 text-sm">
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="text-sm font-medium">Answer</label>
                <input name="answer" required class="w-full border rounded px-2 py-1 text-sm">
            </div>
            <div>
                <label class="text-sm font-medium">Difficulty (1-5)</label>
                <input name="difficulty" type="number" min="1" max="5" value="1" class="w-full border rounded px-2 py-1 text-sm">
            </div>
        </div>
        <div>
            <label class="text-sm font-medium">Explanation (optional)</label>
            <input name="explanation" class="w-full border rounded px-2 py-1 text-sm">
        </div>
        <button class="btn btn-primary">Add Question</button>
    </form>

    <div class="surface">
        <ul class="divide-y text-sm">
            @forelse($questions as $q)
                <li class="px-5 py-3 flex items-start justify-between gap-3">
                    <div>
                        <div class="font-medium">{{ $q->question }}</div>
                        <div class="text-xs text-faint">Answer: {{ $q->answer }} · {{ ucfirst(str_replace('_',' ',$q->type)) }} @if($q->subject)· {{ $q->subject->name }} @endif</div>
                    </div>
                    <form method="POST" action="{{ route('teacher.question-bank.destroy', $q) }}">@csrf @method('DELETE')<button class="text-danger text-xs">Delete</button></form>
                </li>
            @empty
                <li class="px-5 py-4 text-faint">No questions yet.</li>
            @endforelse
        </ul>
    </div>
    {{ $questions->links() }}
</x-layouts.studyai>
