@props([
    'action',
    'question' => null,
    'method' => 'POST',
])

@php
    /**
     * Add or edit a quiz question.
     *
     * Options are dynamic, so the correct answer has to be chosen by position
     * rather than value — the radio carries the index, and removing an option
     * above the selected one has to shift it or the key silently points at the
     * wrong answer. That bookkeeping is the whole reason this is a component
     * and not a plain form.
     */
    $isEdit = $question !== null;

    $initial = [
        'question' => old('question', $question->question ?? ''),
        'type' => old('type', $question->type ?? 'multiple-choice'),
        'options' => array_values((array) old('options', $question->options ?? ['', ''])),
        'correct' => (int) old('correct_idx', $question->correct_idx ?? 0),
        'explanation' => old('explanation', $question->explanation ?? ''),
        'difficulty' => (int) old('difficulty', $question->difficulty ?? 1),
    ];
@endphp

<form method="POST" action="{{ $action }}" x-data="questionEditor(@js($initial))">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <p class="text-sm font-medium text-ink">{{ $isEdit ? 'Edit question' : 'New question' }}</p>

    <div class="mt-3 space-y-3">
        <x-ui.field label="Question" name="question" required>
            <textarea name="question" rows="2" class="textarea" required
                      x-model="form.question" placeholder="What are you asking?"></textarea>
        </x-ui.field>

        <div class="grid gap-3 sm:grid-cols-2">
            <x-ui.field label="Type" name="type">
                <select name="type" class="select" x-model="form.type" @change="onTypeChange()">
                    <option value="multiple-choice">Multiple choice</option>
                    <option value="true-false">True / false</option>
                    <option value="fill-blank">Fill in the blank</option>
                    <option value="short-answer">Short answer</option>
                </select>
            </x-ui.field>

            <x-ui.field label="Difficulty" name="difficulty" hint="1 easiest, 5 hardest">
                <select name="difficulty" class="select" x-model.number="form.difficulty">
                    @for ($d = 1; $d <= 5; $d++)
                        <option value="{{ $d }}">{{ $d }}</option>
                    @endfor
                </select>
            </x-ui.field>
        </div>

        {{-- Choices. For a written answer there is nothing to choose between,
             so the single field becomes the model answer instead. --}}
        <div>
            <span class="field-label" x-text="form.type === 'short-answer' ? 'Model answer' : 'Options'"></span>
            <p class="field-hint" x-show="form.type !== 'short-answer'">
                Select the radio beside the correct answer.
            </p>

            <div class="mt-1.5 space-y-2">
                <template x-for="(option, i) in form.options" :key="i">
                    <div class="flex items-center gap-2">
                        <input type="radio" name="correct_idx" class="radio shrink-0"
                               :value="i" :checked="form.correct === i"
                               x-show="form.type !== 'short-answer'"
                               @change="form.correct = i"
                               :aria-label="`Mark option ${i + 1} as correct`">

                        <input type="text" class="input" name="options[]" required
                               x-model="form.options[i]"
                               :placeholder="form.type === 'short-answer'
                                   ? 'The answer you expect'
                                   : `Option ${String.fromCharCode(65 + i)}`">

                        <button type="button" class="btn-icon shrink-0 text-muted hover:text-danger"
                                x-show="form.type !== 'short-answer' && form.options.length > 2"
                                @click="removeOption(i)" title="Remove option">
                            <x-icon name="x" />
                        </button>
                    </div>
                </template>
            </div>

            <button type="button" class="mt-2 text-xs text-accent hover:underline"
                    x-show="form.type !== 'short-answer' && form.options.length < 6"
                    @click="addOption()">
                + Add option
            </button>
        </div>

        <x-ui.field label="Explanation" name="explanation" hint="Shown after answering.">
            <textarea name="explanation" rows="2" class="textarea"
                      x-model="form.explanation" placeholder="Why is that the answer?"></textarea>
        </x-ui.field>
    </div>

    <div class="mt-4 flex justify-end gap-2 border-t border-line pt-3">
        <button type="button" class="btn btn-ghost btn-sm" @click="$dispatch('cancel-question-edit')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm">{{ $isEdit ? 'Save changes' : 'Add question' }}</button>
    </div>
</form>

@once
    @push('scripts')
        <script>
            function questionEditor(initial) {
                return {
                    form: {
                        question: initial.question,
                        type: initial.type,
                        options: initial.options.length ? [...initial.options] : ['', ''],
                        correct: initial.correct,
                        explanation: initial.explanation,
                        difficulty: initial.difficulty,
                    },

                    addOption() {
                        if (this.form.options.length >= 6) return;
                        this.form.options.push('');
                    },

                    /**
                     * Remove an option and keep the answer key pointing at the
                     * same answer.
                     *
                     * Deleting an option above the correct one shifts every
                     * later index down by one; without this the key would
                     * quietly move to a different answer.
                     */
                    removeOption(i) {
                        if (this.form.options.length <= 2) return;

                        this.form.options.splice(i, 1);

                        if (this.form.correct === i) {
                            this.form.correct = 0;          // the answer itself is gone
                        } else if (i < this.form.correct) {
                            this.form.correct--;            // it moved up
                        }
                    },

                    onTypeChange() {
                        if (this.form.type === 'short-answer') {
                            // One field, and it is the answer.
                            this.form.options = [this.form.options[this.form.correct] ?? this.form.options[0] ?? ''];
                            this.form.correct = 0;
                            return;
                        }

                        if (this.form.type === 'true-false') {
                            this.form.options = ['True', 'False'];
                            if (this.form.correct > 1) this.form.correct = 0;
                            return;
                        }

                        // Coming back from short-answer there is only one field;
                        // a choice question needs at least two.
                        while (this.form.options.length < 2) this.form.options.push('');
                    },
                };
            }
        </script>
    @endpush
@endonce
