@php
    /** @var array $stats */
@endphp
<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
    @foreach([
        ['totalSchools', 'Schools'],
        ['totalUsers', 'Users'],
        ['totalMaterials', 'Materials'],
        ['totalExams', 'Exams'],
        ['totalFlashcards', 'Flashcards'],
    ] as [$key, $label])
        <div class="bg-paper border border-line text-center" style="border-radius:3px; padding:1.25rem">
            <div class="text-3xl font-bold text-ink" style="font-family: var(--font-display)">{{ $stats[$key] ?? 0 }}</div>
            <div class="text-xs text-muted mt-1">{{ $label }}</div>
        </div>
    @endforeach
</div>

@if($recentSchools && $recentSchools->isNotEmpty())
<div class="surface">
    <div class="px-5 py-3 border-b font-semibold text-ink">Recent Schools</div>
    <table class="w-full text-sm">
        <thead class="text-left text-muted border-b">
            <tr><th class="px-5 py-2">Name</th><th class="px-5 py-2">Slug</th><th class="px-5 py-2">Members</th><th class="px-5 py-2">Created</th></tr>
        </thead>
        <tbody>
            @foreach($recentSchools as $s)
                <tr class="border-b">
                    <td class="px-5 py-2 font-medium">{{ $s->name }}</td>
                    <td class="px-5 py-2 text-muted">{{ $s->slug }}</td>
                    <td class="px-5 py-2">{{ $s->members_count }}</td>
                    <td class="px-5 py-2 text-muted">{{ $s->created_at->format('M j, Y') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@else
<div class="surface p-8 text-center text-muted">No schools created yet.</div>
@endif
