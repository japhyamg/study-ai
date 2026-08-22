<x-layouts.studyai title="Materials">
    <div class="surface">
        <div class="px-5 py-3 border-b font-semibold text-ink">Study Materials</div>
        <ul class="divide-y text-sm">
            @forelse($materials as $m)
                <li class="px-5 py-3 flex items-center justify-between">
                    <div><div class="font-medium">{{ $m->title }}</div><div class="text-xs text-faint">{{ $m->type }} · {{ $m->classRoom?->name ?? 'General' }}</div></div>
                </li>
            @empty
                <li class="px-5 py-4 text-faint">No materials available.</li>
            @endforelse
        </ul>
        <div class="px-5 py-3 border-t">{{ $materials->links() }}</div>
    </div>
</x-layouts.studyai>
