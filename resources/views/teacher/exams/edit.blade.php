<x-layouts.studyai title="Edit exam" :subtitle="$exam->title"
                   :back-to="route('teacher.exams.show', $exam)" back-label="Back to exam">

    <div class="surface max-w-2xl">
        <form method="POST" action="{{ route('teacher.exams.update', $exam) }}" class="px-6 py-5 space-y-5">
            @csrf @method('PUT')

            @include('teacher.exams._settings-fields', ['exam' => $exam])

            <div class="flex justify-end gap-2 border-t border-line pt-4">
                <x-ui.button href="{{ route('teacher.exams.show', $exam) }}" variant="ghost">Cancel</x-ui.button>
                <x-ui.button type="submit">Save changes</x-ui.button>
            </div>
        </form>
    </div>
</x-layouts.studyai>
