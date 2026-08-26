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
        <div class="stat text-center"><div class="stat-value">{{ $stats[$key] ?? 0 }}</div><div class="stat-label mt-1">{{ $label }}</div></div>
    @endforeach
</div>

@if($recentSchools && $recentSchools->isNotEmpty())
<div class="surface">
    <div class="px-5 py-3 border-b font-semibold text-ink">Recent Schools</div>
    <div class="table-wrap"><table class="w-full text-sm">
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
    </table></div>
</div>
@else
<div class="surface p-8 text-center text-muted">No schools created yet.</div>
@endif
