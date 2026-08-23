<x-layouts.studyai title="Class Materials">
    <a href="{{ route('student.classes') }}" class="text-xs text-accent">← All classes</a>
    <h2 class="font-semibold text-ink mt-2">{{ $enrollment->classArm?->fullName() }}</h2>

    <div class="surface mt-4">
        <div class="px-5 py-3 border-b font-semibold text-ink">Materials</div>
        <ul class="divide-y text-sm">
            @forelse($enrollment->classArm?->materials ?? [] as $m)
                <li class="px-5 py-3 flex items-center justify-between">
                    <div><div class="font-medium">{{ $m->title }}</div><div class="text-xs text-faint">{{ $m->type }}</div></div>
                </li>
            @empty
                <li class="px-5 py-4 text-faint">No materials published yet.</li>
            @endforelse
        </ul>
    </div>
</x-layouts.studyai>
