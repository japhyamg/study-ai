<div class="grid gap-4 sm:grid-cols-2">
    <x-ui.field label="Class level" name="class_level_id" required>
        <select name="class_level_id" class="select" required>
            <option value="">Choose a level…</option>
            @foreach ($levels as $level)
                <option value="{{ $level->id }}" @selected(old('class_level_id', $arm?->class_level_id) === $level->id)>
                    {{ $level->name }}
                </option>
            @endforeach
        </select>
    </x-ui.field>

    <x-ui.field label="Class name" name="name" required hint="The arm, e.g. A or Blue.">
        <input name="name" class="input" value="{{ old('name', $arm?->name) }}" placeholder="A" required>
    </x-ui.field>

    <x-ui.field label="Stream" name="stream">
        @if (! empty($streams))
            <select name="stream" class="select">
                <option value="">None</option>
                @foreach ($streams as $stream)
                    <option value="{{ $stream }}" @selected(old('stream', $arm?->stream) === $stream)>{{ $stream }}</option>
                @endforeach
            </select>
        @else
            <input name="stream" class="input" value="{{ old('stream', $arm?->stream) }}">
        @endif
    </x-ui.field>

    <x-ui.field label="Capacity" name="capacity" required>
        <input name="capacity" type="number" min="1" max="500" class="input"
               value="{{ old('capacity', $arm?->capacity ?? 40) }}" required>
    </x-ui.field>

    <x-ui.field label="Form teacher" name="form_teacher_id" class="sm:col-span-2">
        <select name="form_teacher_id" class="select">
            <option value="">Unassigned</option>
            @foreach ($teachers as $teacher)
                <option value="{{ $teacher->id }}" @selected(old('form_teacher_id', $arm?->form_teacher_id) === $teacher->id)>
                    {{ $teacher->name }}
                </option>
            @endforeach
        </select>
    </x-ui.field>

    <x-ui.field label="Description" name="description" class="sm:col-span-2">
        <textarea name="description" rows="2" class="textarea">{{ old('description', $arm?->description) }}</textarea>
    </x-ui.field>
</div>
