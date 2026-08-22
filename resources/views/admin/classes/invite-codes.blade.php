<x-layouts.studyai title="Invite Codes — {{ $class->name }}">
    <div class="surface">
        <div class="px-5 py-3 border-b">
            <form method="POST" action="{{ route('admin.classes.invite-codes.store', $class) }}" class="flex gap-3 items-end">
                @csrf
                <div><label class="text-xs text-muted block">Max uses</label><input name="max_uses" type="number" class="border rounded px-2 py-1 w-24"></div>
                <div><label class="text-xs text-muted block">Expires at</label><input name="expires_at" type="date" class="border rounded px-2 py-1"></div>
                <button class="px-3 py-1 btn btn-primary text-sm">Generate</button>
            </form>
        </div>
        <table class="w-full text-sm">
            <thead class="text-left text-muted border-b">
                <tr><th class="px-5 py-2">Code</th><th class="px-5 py-2">Uses</th><th class="px-5 py-2">Max</th><th class="px-5 py-2">Expires</th></tr>
            </thead>
            <tbody>
                @forelse($codes as $code)
                    <tr class="border-b">
                        <td class="px-5 py-2 font-mono">{{ $code->code }}</td>
                        <td class="px-5 py-2">{{ $code->used_count }}</td>
                        <td class="px-5 py-2">{{ $code->max_uses ?? '∞' }}</td>
                        <td class="px-5 py-2 text-muted">{{ $code->expires_at?->format('Y-m-d') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-4 text-faint">No invite codes yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.studyai>
