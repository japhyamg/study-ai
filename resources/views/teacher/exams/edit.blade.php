<x-layouts.studyai title="Edit Exam" subtitle="{{ $exam->title }}">
    <div class="surface max-w-2xl">
        <form method="POST" action="{{ route('teacher.exams.update', $exam) }}" class="px-6 py-5 space-y-5">
            @csrf @method('PUT')

            <x-ui.field label="Title" name="title" required>
                <input id="title" name="title" value="{{ old('title', $exam->title) }}" class="input" required>
            </x-ui.field>

            <x-ui.field label="Description" name="description" hint="Optional. Shown to students before they start.">
                <textarea id="description" name="description" rows="3" class="textarea">{{ old('description', $exam->description) }}</textarea>
            </x-ui.field>

            <div class="grid grid-cols-2 gap-4">
                <x-ui.field label="Class" name="class_arm_id" hint="Leave blank to open it to every class.">
                    <select id="class_arm_id" name="class_arm_id" class="select">
                        <option value="">All classes</option>
                        @foreach($classes as $c)
                            <option value="{{ $c->id }}" @selected(old('class_arm_id', $exam->class_arm_id) == $c->id)>{{ $c->fullName() }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field label="Subject" name="subject_id" hint="Lets you pull questions from this subject's bank.">
                    <select id="subject_id" name="subject_id" class="select">
                        <option value="">No subject</option>
                        @foreach($subjects as $s)
                            <option value="{{ $s->id }}" @selected(old('subject_id', $exam->subject_id) == $s->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <x-ui.field label="Duration (min)" name="duration" hint="Blank = untimed.">
                    <input id="duration" name="duration" type="number" min="1" max="600" value="{{ old('duration', $exam->duration) }}" class="input">
                </x-ui.field>

                <x-ui.field label="Pass mark %" name="pass_mark">
                    <input id="pass_mark" name="pass_mark" type="number" step="0.1" min="0" max="100" value="{{ old('pass_mark', $exam->pass_mark) }}" class="input">
                </x-ui.field>

                <x-ui.field label="Max attempts" name="max_attempts">
                    <input id="max_attempts" name="max_attempts" type="number" min="1" max="10" value="{{ old('max_attempts', $exam->max_attempts) }}" class="input">
                </x-ui.field>
            </div>

            <div class="flex justify-end gap-2 pt-1">
                <x-ui.button href="{{ route('teacher.exams.show', $exam) }}" variant="ghost">Cancel</x-ui.button>
                <x-ui.button type="submit">Save changes</x-ui.button>
            </div>
        </form>
    </div>
</x-layouts.studyai>
