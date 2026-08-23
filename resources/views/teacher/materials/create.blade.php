<x-layouts.studyai title="New study guide"
                   subtitle="Upload a document or paste text — AI writes the guide, flashcards and quiz.">

    @php
        // Decide the visible panel server-side too: with [x-cloak] hiding
        // elements until Alpine boots, an x-cloak-only panel leaves an empty
        // card on first paint.
        $mode = old('source_url') ? 'link' : (old('content') ? 'text' : 'file');
    @endphp

    <a href="{{ route('teacher.materials.index') }}" class="text-xs text-accent">← Back to study guides</a>

    @if ($unassigned)
        <div class="mt-4">
            <x-ui.empty icon="users" title="No classes assigned yet"
                        message="You need to be assigned a subject in a class before you can add study material. Ask an administrator to set that up." />
        </div>
    @else

    <form method="POST" action="{{ route('teacher.materials.store') }}" enctype="multipart/form-data"
          class="mt-4 max-w-3xl space-y-5" x-data="materialForm()">
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

        @if (session('status'))
            <div class="rounded-md border border-success/30 bg-success/5 px-4 py-2.5 text-sm text-success">
                {{ session('status') }}
            </div>
        @endif

        {{-- ── Details ── --}}
        <x-ui.card title="Details">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-ui.field label="Title" name="title" required>
                        <input name="title" value="{{ old('title') }}" required
                               class="w-full rounded-md border border-line bg-surface px-3 py-2 text-sm">
                    </x-ui.field>
                </div>

                <x-ui.field label="Class" name="class_arm_id" required>
                    <select name="class_arm_id" required x-model="classArm"
                            class="w-full rounded-md border border-line bg-surface px-3 py-2 text-sm">
                        <option value="">Choose a class</option>
                        @foreach ($classes as $class)
                            <option value="{{ $class->id }}" @selected(old('class_arm_id') === $class->id)>
                                {{ $class->fullName() }}
                            </option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field label="Subject" name="subject_id" required
                            hint="Only the subjects you teach are listed.">
                    <select name="subject_id" required
                            class="w-full rounded-md border border-line bg-surface px-3 py-2 text-sm">
                        <option value="">Choose a subject</option>
                        @foreach ($subjects as $subject)
                            <option value="{{ $subject->id }}"
                                    x-show="allows('{{ $subject->id }}')"
                                    @selected(old('subject_id') === $subject->id)>
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

        {{-- ── Source ── --}}
        <x-ui.card title="Source" subtitle="Where should the content come from?">
            <div class="space-y-4">
                {{-- Mode switch --}}
                <div class="flex gap-1 border-b border-line">
                    @foreach ([['file', 'Upload a file'], ['text', 'Paste text'], ['link', 'Link or video']] as [$value, $label])
                        <button type="button" class="tab-btn" :class="mode === '{{ $value }}' ? 'active' : ''"
                                @click="mode = '{{ $value }}'">{{ $label }}</button>
                    @endforeach
                </div>

                {{-- File --}}
                <div x-show="mode === 'file'" @style(['display: none' => $mode !== 'file'])>
                    <label class="block cursor-pointer rounded-md border border-dashed border-line px-4 py-8 text-center transition-colors hover:border-line-strong"
                           :class="fileName ? 'border-accent/40 bg-accent/5' : ''"
                           @dragover.prevent @drop.prevent="handleDrop($event)">
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
                                <span class="mt-1 block text-xs text-accent">Choose a different file</span>
                            </div>
                        </template>
                    </label>

                    <p class="mt-2 text-xs text-faint">
                        Scanned PDFs have no text layer and cannot be read — paste the text instead.
                    </p>
                </div>

                {{-- Text --}}
                <div x-show="mode === 'text'" @style(['display: none' => $mode !== 'text'])>
                    <textarea name="content" rows="10"
                              class="w-full rounded-md border border-line bg-surface px-3 py-2 text-sm"
                              placeholder="Paste your notes, a lesson plan, or a chapter…">{{ old('content') }}</textarea>
                </div>

                {{-- Link --}}
                <div x-show="mode === 'link'" @style(['display: none' => $mode !== 'link']) class="space-y-4">
                    <x-ui.field label="Source URL" name="source_url">
                        <input name="source_url" type="url" value="{{ old('source_url') }}"
                               placeholder="https://…"
                               class="w-full rounded-md border border-line bg-surface px-3 py-2 text-sm">
                    </x-ui.field>

                    <x-ui.field label="Type" name="type">
                        <select name="type" x-model="linkType"
                                class="w-full rounded-md border border-line bg-surface px-3 py-2 text-sm">
                            <option value="link">Web page</option>
                            <option value="youtube">YouTube</option>
                            <option value="video">Video</option>
                        </select>
                    </x-ui.field>

                    <p class="text-xs text-faint">
                        Links are saved for students to open. AI generation needs text, so paste a
                        transcript as well if you want a guide from a video.
                    </p>

                    <textarea name="content" rows="5"
                              class="w-full rounded-md border border-line bg-surface px-3 py-2 text-sm"
                              placeholder="Optional: paste a transcript or summary…">{{ old('content') }}</textarea>
                </div>
            </div>
        </x-ui.card>

        <div class="flex items-center gap-3">
            <x-ui.button type="submit">Create</x-ui.button>
            <a href="{{ route('teacher.materials.index') }}" class="text-sm text-muted hover:text-ink">Cancel</a>
        </div>

        <p class="text-xs text-faint">
            You'll choose what to generate on the next page, once you can see the extracted text.
        </p>
    </form>

    @endif

    @push('scripts')
        <script>
            function materialForm() {
                return {
                    mode: @js($mode),
                    linkType: @js(old('type', 'link')),
                    fileName: '',
                    classArm: @js(old('class_arm_id', '')),

                    // subject ids the teacher may use, keyed by class arm
                    subjectsByClass: @js($subjectsByClass),

                    /**
                     * Narrow the subject list once a class is chosen, so a
                     * teacher who takes Maths in one class and English in
                     * another is not offered the wrong pairing.
                     */
                    allows(subjectId) {
                        if (!this.classArm) return true;
                        const allowed = this.subjectsByClass[this.classArm];
                        return !allowed || allowed.includes(subjectId);
                    },

                    handleDrop(event) {
                        const input = event.currentTarget.querySelector('input[type=file]');
                        if (event.dataTransfer.files.length && input) {
                            input.files = event.dataTransfer.files;
                            this.fileName = event.dataTransfer.files[0].name;
                        }
                    },
                };
            }
        </script>
    @endpush
</x-layouts.studyai>
