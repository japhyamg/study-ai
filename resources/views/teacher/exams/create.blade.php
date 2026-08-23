<x-layouts.studyai title="New Exam">
    <a href="{{ route('teacher.exams.index') }}" class="mb-3 inline-block text-xs text-accent">← All exams</a>

    <p class="mb-4 max-w-prose text-sm text-muted">
        Set the basics now — you can add questions and change any of this before you publish.
    </p>

    <div class="surface max-w-2xl">
        <form method="POST" action="{{ route('teacher.exams.store') }}" class="px-6 py-5 space-y-5">
            @csrf

            @include('teacher.exams._settings-fields', ['exam' => null])

            <div class="flex justify-end gap-2 border-t border-line pt-4">
                <x-ui.button href="{{ route('teacher.exams.index') }}" variant="ghost">Cancel</x-ui.button>
                <x-ui.button type="submit">Create exam</x-ui.button>
            </div>
        </form>
    </div>
</x-layouts.studyai>
