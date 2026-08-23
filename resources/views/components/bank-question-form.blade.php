@props([
    'action',
    'question' => null,
    'subject' => null,
    'method' => 'POST',
])

@php
    /**
     * Add or edit a banked question.
     *
     * The fields follow the type, because the types genuinely differ: a
     * multiple-choice question needs a list of options and one marked correct,
     * true/false needs no options at all, and a written answer needs only the
     * answer. Showing all of it at once and hoping the teacher ignores the
     * irrelevant parts is how banks fill up with half-filled rows.
     *
     * The correct answer is stored as text, so it is resolved from the chosen
     * index on submit — options can be reordered later, and an index into a
     * list that has since changed points at the wrong answer.
     */
    $isEdit = $question !== null;

    $options = array_values((array) ($question->options ?? ['', '']));

    // The stored answer is text; find which option it matches so the right
    // radio starts selected.
    $correct = 0;

    foreach ($options as $i => $option) {
        if ($question && $option === $question->answer) {
            $correct = $i;
            break;
        }
    }

    $initial = [
        'question' => old('question', $question->question ?? ''),
        'type' => old('type', $question->type ?? 'mcq'),
        'options' => $options ?: ['', ''],
        'correct' => (int) old('correct_idx', $correct),
        'answer' => old('answer', $question && ! $question->options ? $question->answer : ''),
        'explanation' => old('explanation', $question->explanation ?? ''),
        'difficulty' => (int) old('difficulty', $question->difficulty ?? 1),
    ];
@endphp

<form method="POST" action="{{ $action }}" x-data="bankQuestionForm(@js($initial))">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    @if ($subject)
        <input type="hidden" name="subject_id" value="{{ $subject->id }}">
    @endif

    <p class="text-sm font-medium text-ink">{{ $isEdit ? 'Edit question' : 'New question' }}</p>

    <div class="mt-3 space-y-3">
        <x-ui.field label="Question" name="question" required>
            <textarea name="question" rows="2" class="textarea" required x-model="form.question"></textarea>
        </x-ui.field>

        <div class="grid gap-3 sm:grid-cols-2">
            <x-ui.field label="Type" name="type">
                <select name="type" class="select" x-model="form.type" @change="onTypeChange()">
                    <option value="mcq">Multiple choice</option>
                    <option value="true_false">True / false</option>
                    <option value="fill_blank">Fill in the blank</option>
                    <option value="short_answer">Short answer</option>
                    <option value="essay">Essay</option>
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

        {{-- Choice types: options with one marked correct. --}}
        <div x-show="choiceBased()" x-cloak>
            <span class="field-label">Options</span>
            <p class="field-hint">Select the radio beside the correct answer.</p>

            <div class="mt-1.5 space-y-2">
                <template x-for="(option, i) in form.options" :key="i">
                    <div class="flex items-center gap-2">
                        <input type="radio" name="correct_idx" class="radio shrink-0"
                               :value="i" :checked="form.correct === i"
                               @change="form.correct = i"
                               :aria-label="`Mark option ${i + 1} as correct`">

                        <input type="text" class="input" name="options[]"
                               x-model="form.options[i]"
                               :readonly="form.type === 'true_false'"
                               :placeholder="`Option ${String.fromCharCode(65 + i)}`">

                        <button type="button" class="btn-icon shrink-0 text-muted hover:text-danger"
                                x-show="form.type === 'mcq' && form.options.length > 2"
                                @click="removeOption(i)" title="Remove option">
                            <x-icon name="x" />
                        </button>
                    </div>
                </template>
            </div>

            <button type="button" class="mt-2 text-xs text-accent hover:underline"
                    x-show="form.type === 'mcq' && form.options.length < 6"
                    @click="addOption()">
                + Add option
            </button>
        </div>

        {{-- Written types: just the expected answer. --}}
        <div x-show="! choiceBased()" x-cloak>
            <x-ui.field label="Expected answer" name="answer"
                        hint="What a correct response should say.">
                <textarea name="answer" rows="2" class="textarea" x-model="form.answer"></textarea>
            </x-ui.field>
        </div>

        <x-ui.field label="Explanation" name="explanation" hint="Optional. Shown after answering.">
            <textarea name="explanation" rows="2" class="textarea" x-model="form.explanation"></textarea>
        </x-ui.field>
    </div>

    <div class="mt-4 flex justify-end gap-2 border-t border-line pt-3">
        {{-- Dispatch rather than assigning to a parent variable: this form is
             used both inline (parent has `editing`) and in the add panel
             (parent has `adding`), and referencing a name the parent does not
             define throws in Alpine. --}}
        <button type="button" class="btn btn-ghost btn-sm"
                @click="$dispatch('bank-form-cancel')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm">
            {{ $isEdit ? 'Save changes' : 'Add question' }}
        </button>
    </div>
</form>

@once
    @push('scripts')
        <script>
            function bankQuestionForm(initial) {
                return {
                    form: {
                        question: initial.question,
                        type: initial.type,
                        options: initial.options.length ? [...initial.options] : ['', ''],
                        correct: initial.correct,
                        answer: initial.answer,
                        explanation: initial.explanation,
                        difficulty: initial.difficulty,
                    },

                    // Only these two are answered by picking from a list.
                    choiceBased() {
                        return this.form.type === 'mcq' || this.form.type === 'true_false';
                    },

                    addOption() {
                        if (this.form.options.length >= 6) return;
                        this.form.options.push('');
                    },

                    /**
                     * Remove an option and keep the answer pointing at the same
                     * text. Deleting an option above the correct one shifts
                     * every later index down; without this the key silently
                     * moves to a different answer.
                     */
                    removeOption(i) {
                        if (this.form.options.length <= 2) return;

                        this.form.options.splice(i, 1);

                        if (this.form.correct === i) this.form.correct = 0;
                        else if (i < this.form.correct) this.form.correct--;
                    },

                    onTypeChange() {
                        if (this.form.type === 'true_false') {
                            this.form.options = ['True', 'False'];
                            if (this.form.correct > 1) this.form.correct = 0;
                            return;
                        }

                        if (this.form.type === 'mcq') {
                            // Coming back from a written type there may be
                            // nothing to choose between yet.
                            while (this.form.options.length < 2) this.form.options.push('');
                            if (this.form.correct >= this.form.options.length) this.form.correct = 0;
                        }
                    },
                };
            }
        </script>
    @endpush
@endonce
