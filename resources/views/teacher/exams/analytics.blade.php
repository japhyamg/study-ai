<x-layouts.studyai title="Analytics" :subtitle="$exam->title"
                   :back-to="route('teacher.exams.show', $exam)" back-label="Back to exam">

    <div class="grid grid-cols-3 gap-4">
        <div class="surface p-4"><div class="text-xs text-faint">Average score</div><div class="text-2xl font-semibold">{{ number_format($avg, 1) }}%</div></div>
        <div class="surface p-4"><div class="text-xs text-faint">Pass rate</div><div class="text-2xl font-semibold">{{ $total > 0 ? number_format($passRate / $total * 100, 1) : 0 }}%</div></div>
        <div class="surface p-4"><div class="text-xs text-faint">Attempts</div><div class="text-2xl font-semibold">{{ $total }}</div></div>
    </div>

    <div class="surface mt-4">
        <div class="px-5 py-3 border-b font-semibold text-ink">Attempts</div>
        <table class="w-full text-sm">
            <thead class="bg-paper-sunk text-left text-muted">
                <tr><th class="px-4 py-2">Student</th><th class="px-4 py-2">Score</th><th class="px-4 py-2">%</th><th class="px-4 py-2">Result</th><th class="px-4 py-2">Date</th></tr>
            </thead>
            <tbody class="divide-y">
                @forelse($attempts as $a)
                    <tr>
                        <td class="px-4 py-2">{{ $a->user?->name ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $a->score }}/{{ $a->max_score }}</td>
                        <td class="px-4 py-2">{{ $a->percentage }}%</td>
                        <td class="px-4 py-2">{{ $a->passed ? 'Pass' : 'Fail' }}</td>
                        <td class="px-4 py-2 text-xs text-faint">{{ $a->created_at?->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-faint">No attempts yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $attempts->links() }}
</x-layouts.studyai>
