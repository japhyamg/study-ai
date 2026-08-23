<x-layouts.studyai title="New Exam" subtitle="Set the basics now — you can add questions and change any of this before you publish.">
    <div class="surface max-w-2xl">
        <form method="POST" action="{{ route('teacher.exams.store') }}" class="px-6 py-5 space-y-5">
            @csrf

            <x-ui.field label="Title" name="title" required>
                <input id="title" name="title" value="{{ old('title') }}" class="input" required>
            </x-ui.field>

            <x-ui.field label="Description" name="description" hint="Optional. Shown to students before they start.">
                <textarea id="description" name="description" rows="3" class="textarea">{{ old('description') }}</textarea>
            </x-ui.field>

            <div class="grid grid-cols-2 gap-4">
                <x-ui.field label="Class" name="class_arm_id" hint="Leave blank to open it to every class.">
                    <select id="class_arm_id" name="class_arm_id" class="select">
                        <option value="">All classes</option>
                        @foreach($classes as $c)
                            <option value="{{ $c->id }}" @selected(old('class_arm_id') == $c->id)>{{ $c->fullName() }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field label="Subject" name="subject_id" hint="Lets you pull questions from this subject's bank.">
                    <select id="subject_id" name="subject_id" class="select">
                        <option value="">No subject</option>
                        @foreach($subjects as $s)
                            <option value="{{ $s->id }}" @selected(old('subject_id') == $s->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <x-ui.field label="Duration (min)" name="duration" hint="Blank = untimed.">
                    <input id="duration" name="duration" type="number" min="1" max="600" value="{{ old('duration') }}" class="input">
                </x-ui.field>

                <x-ui.field label="Pass mark %" name="pass_mark">
                    <input id="pass_mark" name="pass_mark" type="number" step="0.1" min="0" max="100" value="{{ old('pass_mark', 50) }}" class="input">
                </x-ui.field>

                <x-ui.field label="Max attempts" name="max_attempts">
                    <input id="max_attempts" name="max_attempts" type="number" min="1" max="10" value="{{ old('max_attempts', 1) }}" class="input">
                </x-ui.field>
            </div>

            <div class="flex justify-end gap-2 pt-1">
                <x-ui.button href="{{ route('teacher.exams.index') }}" variant="ghost">Cancel</x-ui.button>
                <x-ui.button type="submit">Create exam</x-ui.button>
            </div>
        </form>
    </div>
</x-layouts.studyai>
