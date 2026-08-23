<x-layouts.studyai title="New exam"
                   subtitle="Set the basics now — you can add questions and change any of this before you publish."
                   :back-to="route('teacher.exams.index')" back-label="All exams">

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
