<x-layouts.studyai title="New Class">
    <div class="surface max-w-2xl">
        <form method="POST" action="{{ route('admin.classes.store') }}" class="px-6 py-5 space-y-4">
            @csrf
            <div>
                <label class="text-xs text-muted block">Name</label>
                <input name="name" class="w-full border rounded px-2 py-1" required>
            </div>
            <div>
                <label class="text-xs text-muted block">Description</label>
                <textarea name="description" class="w-full border rounded px-2 py-1"></textarea>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="text-xs text-muted block">Subject</label>
                    <select name="subject_id" class="w-full border rounded px-2 py-1">
                        <option value="">—</option>
                        @foreach($subjects as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-muted block">Term</label>
                    <select name="term_id" class="w-full border rounded px-2 py-1">
                        <option value="">—</option>
                        @foreach($terms as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-muted block">Teacher</label>
                    <select name="teacher_id" class="w-full border rounded px-2 py-1">
                        <option value="">—</option>
                        @foreach($teachers as $m)<option value="{{ $m->user_id }}">{{ $m->user?->name }}</option>@endforeach
                    </select>
                </div>
            </div>
            <button class="px-4 py-1.5 btn btn-primary text-sm">Create Class</button>
        </form>
    </div>
</x-layouts.studyai>
