<x-layouts.studyai title="New Exam">
    <div class="surface max-w-2xl">
        <form method="POST" action="{{ route('teacher.exams.store') }}" class="px-6 py-5 space-y-4">
            @csrf
            <div><label class="text-xs text-muted block">Title</label><input name="title" class="w-full border rounded px-2 py-1" required></div>
            <div><label class="text-xs text-muted block">Description</label><textarea name="description" class="w-full border rounded px-2 py-1"></textarea></div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="text-xs text-muted block">Class</label>
                    <select name="class_arm_id" class="w-full border rounded px-2 py-1">
                        <option value="">—</option>
                        @foreach($classes as $c)<option value="{{ $c->id }}">{{ $c->fullName() }}</option>@endforeach
                    </select>
                </div>
                <div><label class="text-xs text-muted block">Duration (min)</label><input name="duration_minutes" type="number" class="w-full border rounded px-2 py-1"></div>
                <div><label class="text-xs text-muted block">Pass mark %</label><input name="pass_mark" type="number" step="0.1" value="50" class="w-full border rounded px-2 py-1"></div>
            </div>
            <button class="px-4 py-1.5 btn btn-primary text-sm">Create Exam</button>
        </form>
    </div>
</x-layouts.studyai>
