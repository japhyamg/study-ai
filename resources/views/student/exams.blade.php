<x-layouts.studyai title="Exams">
    <div class="surface">
        <div class="px-5 py-3 border-b font-semibold text-ink">Published Exams</div>
        <ul class="divide-y text-sm">
            @forelse($exams as $e)
                <li class="px-5 py-3 flex items-center justify-between gap-3">
                    <div>
                        <div class="font-medium">{{ $e->title }}</div>
                        <div class="text-xs text-faint">{{ $e->classArm?->fullName() ?? 'General' }} · {{ $e->questions_count }} questions @if($e->duration)· {{ $e->duration }} min @endif</div>
                    </div>
                    <form method="POST" action="{{ route('student.exams.start', $e) }}">@csrf<button class="px-3 py-1 btn btn-primary text-xs">Start</button></form>
                </li>
            @empty
                <li class="px-5 py-4 text-faint">No exams available.</li>
            @endforelse
        </ul>
        <div class="px-5 py-3 border-t">{{ $exams->links() }}</div>
    </div>
</x-layouts.studyai>
