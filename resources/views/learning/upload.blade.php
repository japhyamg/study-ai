<x-layouts.studyai title="Upload material"
                   subtitle="Turn a document into a study guide, flashcards and a quiz.">

    <form method="POST" action="{{ route('learning.upload.store') }}" enctype="multipart/form-data"
          class="max-w-3xl space-y-5" x-data="uploadForm()">
        @csrf

        @if ($errors->any())
            <div class="rounded-md border border-danger/30 bg-danger/5 px-4 py-3 text-sm text-danger">
                <ul class="space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-ui.card title="Source" subtitle="Upload a file, or paste the text yourself.">
            <div class="space-y-4">
                {{-- Drop zone --}}
                <label class="block cursor-pointer rounded-md border border-dashed border-line px-4 py-8 text-center transition-colors hover:border-line-strong"
                       :class="fileName ? 'border-accent/40 bg-accent/5' : ''"
                       @dragover.prevent="dragging = true"
                       @dragleave.prevent="dragging = false"
                       @drop.prevent="handleDrop($event)">
                    <input type="file" name="document" class="sr-only"
                           accept=".pdf,.docx,.doc,.txt,.md,.csv"
                           @change="fileName = $event.target.files[0]?.name ?? ''">

                    <template x-if="!fileName">
                        <div>
                            <div class="text-sm text-ink">Drop a file here, or click to choose</div>
                            <div class="mt-1 text-xs text-faint">
                                PDF, Word, or plain text · up to {{ (int) (config('ai.uploads.max_size_kb', 20480) / 1024) }} MB
                            </div>
                        </div>
                    </template>

                    <template x-if="fileName">
                        <div>
                            <div class="text-sm font-medium text-ink" x-text="fileName"></div>
                            <button type="button" class="mt-1 text-xs text-accent"
                                    @click.prevent="clearFile($event)">Choose a different file</button>
                        </div>
                    </template>
                </label>

                <div class="flex items-center gap-3 text-xs text-faint">
                    <span class="h-px flex-1 bg-line"></span>
                    or paste text
                    <span class="h-px flex-1 bg-line"></span>
                </div>

                <textarea name="content" rows="7"
                          class="w-full rounded-md border border-line bg-surface px-3 py-2 text-sm"
                          placeholder="Paste your notes, a lesson plan, or a chapter…">{{ old('content') }}</textarea>

                <p class="text-xs text-faint">
                    Scanned PDFs have no text layer and cannot be read — paste the text instead.
                </p>
            </div>
        </x-ui.card>

        <x-ui.card title="Details">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-ui.field label="Title" name="title" required>
                        <input name="title" value="{{ old('title') }}" required
                               class="w-full rounded-md border border-line bg-surface px-3 py-2 text-sm">
                    </x-ui.field>
                </div>

                <x-ui.field label="Class" name="class_arm_id" hint="Leave blank to share with the whole school.">
                    <select name="class_arm_id" class="w-full rounded-md border border-line bg-surface px-3 py-2 text-sm">
                        <option value="">All classes</option>
                        @foreach ($classes as $class)
                            <option value="{{ $class->id }}" @selected(old('class_arm_id') === $class->id)>
                                {{ $class->fullName() }}
                            </option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field label="Subject" name="subject_id">
                    <select name="subject_id" class="w-full rounded-md border border-line bg-surface px-3 py-2 text-sm">
                        <option value="">No subject</option>
                        @foreach ($subjects as $subject)
                            <option value="{{ $subject->id }}" @selected(old('subject_id') === $subject->id)>
                                {{ $subject->name }}
                            </option>
                        @endforeach
                    </select>
                </x-ui.field>

                <div class="sm:col-span-2">
                    <x-ui.field label="Description" name="description">
                        <textarea name="description" rows="2"
                                  class="w-full rounded-md border border-line bg-surface px-3 py-2 text-sm">{{ old('description') }}</textarea>
                    </x-ui.field>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card title="What to generate">
            <div class="space-y-4">
                <label class="flex items-start gap-3">
                    <input type="checkbox" name="generate" value="1" checked x-model="generate" class="mt-0.5">
                    <span>
                        <span class="text-sm text-ink">Generate study content now</span>
                        <span class="block text-xs text-faint">
                            A study guide, flashcards and a quiz. Uncheck to save as a draft.
                        </span>
                    </span>
                </label>

                <div x-show="generate" x-cloak class="grid gap-4 border-t border-line pt-4 sm:grid-cols-2">
                    <x-ui.field label="Quiz questions" name="question_count">
                        <div class="flex items-center gap-3">
                            <input type="range" name="question_count" min="3" max="30"
                                   x-model="questionCount" class="flex-1">
                            <span class="tnum w-8 text-sm text-ink" x-text="questionCount"></span>
                        </div>
                    </x-ui.field>

                    <x-ui.field label="Question types" name="question_types">
                        <div class="space-y-1.5">
                            @php $selected = old('question_types', ['multiple-choice']); @endphp
                            @foreach (['multiple-choice' => 'Multiple choice', 'true-false' => 'True / false', 'fill-blank' => 'Fill in the blank', 'short-answer' => 'Short answer'] as $value => $label)
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="question_types[]" value="{{ $value }}"
                                           @checked(in_array($value, $selected, true))>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </x-ui.field>
                </div>
            </div>
        </x-ui.card>

        <div class="flex items-center gap-3">
            <x-ui.button type="submit">Upload</x-ui.button>
            <a href="{{ route('teacher.materials.index') }}" class="text-sm text-muted hover:text-ink">Cancel</a>
        </div>
    </form>

    @push('scripts')
        <script>
            function uploadForm() {
                return {
                    fileName: '',
                    dragging: false,
                    generate: true,
                    questionCount: {{ (int) old('question_count', config('ai.defaults.question_count', 10)) }},

                    handleDrop(event) {
                        this.dragging = false;
                        const input = event.currentTarget.querySelector('input[type=file]');
                        if (event.dataTransfer.files.length && input) {
                            input.files = event.dataTransfer.files;
                            this.fileName = event.dataTransfer.files[0].name;
                        }
                    },

                    clearFile(event) {
                        const input = event.target.closest('label').querySelector('input[type=file]');
                        if (input) input.value = '';
                        this.fileName = '';
                    },
                };
            }
        </script>
    @endpush
</x-layouts.studyai>
