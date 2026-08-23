<x-layouts.studyai title="Exam: {{ $exam->title }}">
    <div class="mb-4 flex gap-2 items-center">
        <a href="{{ route('teacher.exams.edit', $exam) }}" class="px-3 py-1 btn btn-primary text-sm">Edit</a>
        @if($exam->status === 'published')
            <form method="POST" action="{{ route('teacher.exams.unpublish', $exam) }}">@csrf @method('PUT')<button class="px-3 py-1 bg-paper-sunk rounded text-sm">Unpublish</button></form>
        @else
            <form method="POST" action="{{ route('teacher.exams.publish', $exam) }}">@csrf @method('PUT')<button class="px-3 py-1 bg-green-600 text-white rounded text-sm">Publish</button></form>
        @endif
        <form method="POST" action="{{ route('teacher.exams.destroy', $exam) }}" onsubmit="return confirm('Delete exam?')">@csrf @method('DELETE')<button class="px-3 py-1 bg-red-600 text-white rounded text-sm">Delete</button></form>
        <span class="ml-auto text-xs px-2 py-0.5 rounded {{ $exam->status==='published' ? 'bg-green-100 text-ok' : 'bg-paper-sunk text-muted' }}">{{ $exam->status }}</span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="surface p-5 lg:col-span-2">
            <div class="font-semibold text-ink mb-3">Questions ({{ $exam->questions->count() }})</div>
            <ol class="divide-y text-sm space-y-2">
                @forelse($exam->questions as $q)
                    <li class="pt-2 flex items-start justify-between gap-3">
                        <div>
                            <div class="font-medium">{{ $loop->iteration }}. {{ $q->question }}</div>
                            @if($q->options)
                                <ul class="text-xs text-muted list-disc pl-5 mt-1">
                                    @foreach($q->options as $opt)<li>{{ $opt }}{{ $loop->index === (int) $q->answer ? ' ✓' : '' }}</li>@endforeach
                                </ul>
                            @endif
                            <div class="text-xs text-faint mt-1">{{ $q->type }} · {{ $q->points }} pts</div>
                        </div>
                        <form method="POST" action="{{ route('teacher.exams.questions.destroy', [$exam, $q]) }}" onsubmit="return confirm('Remove question?')">@csrf @method('DELETE')<button class="text-xs text-danger">Remove</button></form>
                    </li>
                @empty
                    <li class="py-2 text-faint">No questions yet.</li>
                @endforelse
            </ol>
        </div>

        <div class="space-y-5 h-fit">
            {{-- ── From the subject's bank ──
                 Everything approved for this subject over time, grouped by the
                 study guide it came from. Questions already on the exam are
                 filtered out server-side. --}}
            <div class="surface p-5">
                <div class="mb-1 font-semibold text-ink">Add from question bank</div>

                @if ($questionBank->isEmpty())
                    <p class="text-xs text-faint">
                        Nothing available yet. Questions join the bank for a subject once an
                        admin approves the study guide they came from.
                    </p>
                @else
                    <p class="mb-3 text-xs text-faint">
                        {{ $questionBank->flatten()->count() }} approved
                        {{ Str::plural('question', $questionBank->flatten()->count()) }} for this subject.
                    </p>

                    <form method="POST" action="{{ route('teacher.exams.questions.from-bank', $exam) }}"
                          x-data="{ picked: 0 }">
                        @csrf
                        <div class="max-h-80 space-y-3 overflow-y-auto pe-1">
                            @foreach ($questionBank as $topic => $rows)
                                <div>
                                    <p class="mb-1 text-xs font-medium text-muted">{{ $topic }}</p>
                                    <div class="space-y-1">
                                        @foreach ($rows as $row)
                                            <label class="flex cursor-pointer items-start gap-2 rounded-md p-1.5 text-xs transition-colors hover:bg-surface-sunk">
                                                <input type="checkbox" name="bank_ids[]" value="{{ $row->id }}"
                                                       class="checkbox mt-0.5 shrink-0"
                                                       @change="picked += $event.target.checked ? 1 : -1">
                                                <span class="min-w-0 text-ink">{{ Str::limit($row->question, 110) }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <button type="submit" class="btn btn-primary btn-sm mt-3 w-full" :disabled="picked === 0">
                            Add <span x-show="picked > 0" x-cloak><span x-text="picked"></span></span> selected
                        </button>
                    </form>
                @endif
            </div>

        <div class="surface p-5 h-fit">
            <div class="font-semibold text-ink mb-3">Or write a new question</div>
            <form method="POST" action="{{ route('teacher.exams.questions.store', $exam) }}" class="space-y-3">
                @csrf
                <div><label class="text-xs text-muted block">Question</label><textarea name="question" class="w-full border rounded px-2 py-1" rows="3" required></textarea></div>
                <div><label class="text-xs text-muted block">Type</label>
                    <select name="type" class="w-full border rounded px-2 py-1">
                        <option value="mcq">Multiple choice</option>
                        <option value="true_false">True / False</option>
                        <option value="fill_blank">Fill blank</option>
                        <option value="short_answer">Short answer</option>
                    </select>
                </div>
                <div><label class="text-xs text-muted block">Options (one per line)</label><textarea name="options" class="w-full border rounded px-2 py-1" rows="4"></textarea></div>
                <div><label class="text-xs text-muted block">Correct answer (text or option index)</label><input name="answer" class="w-full border rounded px-2 py-1"></div>
                <div><label class="text-xs text-muted block">Explanation</label><textarea name="explanation" class="w-full border rounded px-2 py-1" rows="2"></textarea></div>
                <div><label class="text-xs text-muted block">Points</label><input name="points" type="number" step="0.5" value="1" class="w-full border rounded px-2 py-1"></div>
                <button class="px-3 py-1 btn btn-primary text-sm">Add</button>
            </form>
        </div>
        </div>
    </div>
</x-layouts.studyai>
