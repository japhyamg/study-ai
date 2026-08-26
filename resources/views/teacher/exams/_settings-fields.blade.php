{{--
    The exam settings shared by the create and edit forms.

    Every control here maps to behaviour the app enforces, so the two forms
    cannot drift apart as settings are added.

    $exam is null when creating.
--}}
@php
    $exam = $exam ?? null;

    // datetime-local wants "Y-m-d\TH:i" and no timezone suffix.
    $dt = fn ($value) => $value?->format('Y-m-d\TH:i');
@endphp

<x-ui.field label="Title" name="title" required>
    <input id="title" name="title" value="{{ old('title', $exam->title ?? '') }}" class="input" required>
</x-ui.field>

<x-ui.field label="Description" name="description" hint="Optional. Shown to students before they start.">
    <textarea id="description" name="description" rows="3" class="textarea">{{ old('description', $exam->description ?? '') }}</textarea>
</x-ui.field>

<div class="grid gap-4 sm:grid-cols-2">
    <x-ui.field label="Class" name="class_arm_id" hint="Leave blank to open it to every class.">
        <select id="class_arm_id" name="class_arm_id" class="select">
            <option value="">All classes</option>
            @foreach($classes as $c)
                <option value="{{ $c->id }}" @selected(old('class_arm_id', $exam->class_arm_id ?? null) == $c->id)>{{ $c->fullName() }}</option>
            @endforeach
        </select>
    </x-ui.field>

    <x-ui.field label="Subject" name="subject_id" hint="Lets you pull questions from this subject's bank.">
        <select id="subject_id" name="subject_id" class="select">
            <option value="">No subject</option>
            @foreach($subjects as $s)
                <option value="{{ $s->id }}" @selected(old('subject_id', $exam->subject_id ?? null) == $s->id)>{{ $s->name }}</option>
            @endforeach
        </select>
    </x-ui.field>
</div>

<div class="grid gap-4 sm:grid-cols-3">
    <x-ui.field label="Duration (min)" name="duration" hint="Blank = untimed.">
        <input id="duration" name="duration" type="number" min="1" max="600"
               value="{{ old('duration', $exam->duration ?? null) }}" class="input">
    </x-ui.field>

    <x-ui.field label="Pass mark %" name="pass_mark">
        <input id="pass_mark" name="pass_mark" type="number" step="0.1" min="0" max="100"
               value="{{ old('pass_mark', $exam->pass_mark ?? 50) }}" class="input">
    </x-ui.field>

    <x-ui.field label="Max attempts" name="max_attempts">
        <input id="max_attempts" name="max_attempts" type="number" min="1" max="10"
               value="{{ old('max_attempts', $exam->max_attempts ?? 1) }}" class="input">
    </x-ui.field>
</div>

{{-- ── Availability window ── --}}
<div class="border-t border-line pt-4">
    <p class="text-sm font-medium text-ink">Availability</p>
    <p class="field-hint mb-3">
        Optional. Students cannot start before it opens or after it closes.
        An attempt already in progress is not cut short by the closing time —
        the duration timer handles that.
    </p>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui.field label="Opens" name="start_time" hint="Leave blank to open as soon as it is published.">
            <input id="start_time" name="start_time" type="datetime-local" class="input"
                   value="{{ old('start_time', $dt($exam->start_time ?? null)) }}">
        </x-ui.field>

        <x-ui.field label="Closes" name="end_time" hint="Leave blank for no closing time.">
            <input id="end_time" name="end_time" type="datetime-local" class="input"
                   value="{{ old('end_time', $dt($exam->end_time ?? null)) }}">
        </x-ui.field>
    </div>
</div>

{{-- ── Shuffling ── --}}
<div class="border-t border-line pt-4">
    <p class="text-sm font-medium text-ink">Shuffling</p>
    <p class="field-hint mb-3">
        Each student gets their own order, fixed for the whole attempt, so
        refreshing the page never rearranges the paper.
    </p>

    <div class="space-y-2">
        <label class="flex items-start gap-2.5 text-sm">
            <input type="checkbox" name="shuffle_questions" value="1" class="mt-0.5 shrink-0"
                   @checked(old('shuffle_questions', $exam->shuffle_questions ?? false))>
            <span>
                <span class="text-ink">Shuffle question order</span>
                <span class="block text-xs text-faint">Questions appear in a different order per student.</span>
            </span>
        </label>

        <label class="flex items-start gap-2.5 text-sm">
            <input type="checkbox" name="shuffle_options" value="1" class="mt-0.5 shrink-0"
                   @checked(old('shuffle_options', $exam->shuffle_options ?? false))>
            <span>
                <span class="text-ink">Shuffle answer options</span>
                <span class="block text-xs text-faint">
                    Multiple-choice options are reordered. True/false is left alone.
                </span>
            </span>
        </label>
    </div>
</div>
