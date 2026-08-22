<x-layouts.studyai title="Edit Material">
    <a href="{{ route('teacher.materials.show', $material) }}" class="text-xs text-accent">← Back</a>
    <h2 class="font-semibold text-ink mt-2">Edit: {{ $material->title }}</h2>

    @if(session('status'))<div class="text-ok text-sm mb-3">{{ session('status') }}</div>@endif

    <form method="POST" action="{{ route('teacher.materials.update', $material) }}" class="surface p-5 space-y-4 max-w-2xl">
        @csrf @method('PUT')
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="text-sm font-medium">Class</label>
                <select name="class_id" class="w-full border rounded px-2 py-1 text-sm">
                    <option value="">No class</option>
                    @foreach($classes as $c)<option value="{{ $c->id }}" @if($c->id===$material->class_id) selected @endif>{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="text-sm font-medium">Subject</label>
                <select name="subject_id" class="w-full border rounded px-2 py-1 text-sm">
                    <option value="">None</option>
                    @foreach($subjects as $s)<option value="{{ $s->id }}" @if($s->id===$material->subject_id) selected @endif>{{ $s->name }}</option>@endforeach
                </select>
            </div>
        </div>
        <div>
            <label class="text-sm font-medium">Title</label>
            <input name="title" required class="w-full border rounded px-2 py-1 text-sm" value="{{ old('title', $material->title) }}">
        </div>
        <div>
            <label class="text-sm font-medium">Description</label>
            <textarea name="description" rows="2" class="w-full border rounded px-2 py-1 text-sm">{{ old('description', $material->description) }}</textarea>
        </div>
        <div>
            <label class="text-sm font-medium">Content / Transcript</label>
            <textarea name="content" rows="6" class="w-full border rounded px-2 py-1 text-sm font-mono">{{ old('content', $material->content) }}</textarea>
        </div>
        <div>
            <label class="text-sm font-medium">Status</label>
            <select name="status" class="w-full border rounded px-2 py-1 text-sm">
                <option value="draft" @if($material->status==='draft') selected @endif>Draft</option>
                <option value="processing" @if($material->status==='processing') selected @endif>Processing</option>
                <option value="ready" @if($material->status==='ready') selected @endif>Ready</option>
                <option value="failed" @if($material->status==='failed') selected @endif>Failed</option>
            </select>
        </div>
        <button class="btn btn-primary">Save</button>
    </form>
</x-layouts.studyai>
