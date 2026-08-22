<x-layouts.studyai title="Material Review">
    <div class="surface">
        <div class="px-5 py-3 border-b font-semibold text-ink">Materials Awaiting Review</div>
        <div class="table-wrap"><table class="w-full text-sm">
            <thead class="text-left text-muted border-b">
                <tr><th class="px-5 py-2">Title</th><th class="px-5 py-2">Class</th><th class="px-5 py-2">Type</th><th class="px-5 py-2">Status</th><th class="px-5 py-2"></th></tr>
            </thead>
            <tbody>
                @forelse($materials as $m)
                    <tr class="border-b">
                        <td class="px-5 py-2">{{ $m->title }}</td>
                        <td class="px-5 py-2 text-muted">{{ $m->classRoom?->name ?? '—' }}</td>
                        <td class="px-5 py-2">{{ $m->type }}</td>
                        <td class="px-5 py-2">
                            <span class="px-2 py-0.5 rounded text-xs
                                {{ $m->review_status==='approved' ? 'bg-green-100 text-ok' : ($m->review_status==='rejected' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700') }}">
                                {{ $m->review_status }}
                            </span>
                        </td>
                        <td class="px-5 py-2 text-right whitespace-nowrap">
                            @if($m->review_status !== 'approved')
                                <form method="POST" action="{{ route('teacher.materials.approve', $m) }}" class="inline">@csrf @method('PUT')<button class="text-xs text-ok">Approve</button></form>
                            @endif
                            @if($m->review_status !== 'rejected')
                                <form method="POST" action="{{ route('teacher.materials.reject', $m) }}" class="inline" onsubmit="return confirm('Reject this material?')">@csrf @method('PUT')<button class="text-xs text-danger">Reject</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-4 text-faint">No materials.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        <div class="px-5 py-3 border-t">{{ $materials->links() }}</div>
    </div>
</x-layouts.studyai>
