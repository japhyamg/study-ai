<x-layouts.studyai title="My Classes">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($enrollments as $en)
            <a href="{{ route('student.classes.show', $en) }}" class="block surface p-4 hover:shadow-md transition">
                <div class="font-semibold text-ink">{{ $en->class->name }}</div>
                <div class="text-xs text-faint mt-1">{{ $en->class->enrollments_count }} students · {{ $en->class->materials_count ?? 0 }} materials</div>
                @if($en->class->subject)<div class="text-xs text-muted mt-1">{{ $en->class->subject->name }}</div>@endif
            </a>
        @empty
            <div class="col-span-full text-faint text-sm">You are not enrolled in any classes yet.</div>
        @endforelse
    </div>
</x-layouts.studyai>
