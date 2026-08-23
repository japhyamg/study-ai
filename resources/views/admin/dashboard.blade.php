<x-layouts.studyai title="Admin Dashboard" :subtitle="$school->name ?? 'School'">

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        @foreach([
            ['classes', 'Total Classes', '🏫'],
            ['students', 'Total Students', '🎓'],
            ['exams', 'Total Exams', '📝'],
            ['avgScore', 'Avg Score', '📊'],
        ] as [$key, $label, $emoji])
            <div class="bg-paper border border-line text-center" style="border-radius:3px; padding:1.25rem">
                <div class="text-3xl mb-1">{{ $emoji }}</div>
                <div class="text-3xl font-bold text-ink" style="font-family: var(--font-display)">
                    @if($key === 'avgScore'){{ $stats[$key] }}%
                    @else{{ $stats[$key] }}
                    @endif
                </div>
                <div class="text-xs text-muted mt-1">{{ $label }}</div>
            </div>
        @endforeach
    </div>

    {{-- Recent Activity + Recent Exams (two columns) --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Recent Activity --}}
        <div class="surface">
            <div class="px-5 py-3 border-b font-semibold text-ink">Recent Activity</div>
            @if($recentActivity->isEmpty())
                <div class="px-5 py-8 text-center text-faint">No recent activity.</div>
            @else
                <ul class="divide-y">
                    @foreach($recentActivity as $a)
                        <li class="px-5 py-2 text-sm">
                            <span class="text-muted">
                                @if($a['type'] === 'join')
                                    {{ $a['user'] }} joined the school
                                @else
                                    {{ $a['text'] }}
                                @endif
                            </span>
                            <span class="text-xs text-faint float-right">{{ $a['time'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Recent Exams --}}
        <div class="surface">
            <div class="px-5 py-3 border-b font-semibold text-ink">Recent Exams</div>
            @if($recentExamsTable->isEmpty())
                <div class="px-5 py-8 text-center text-faint">No exams yet.</div>
            @else
                <table class="w-full text-sm">
                    <thead class="text-left text-muted border-b">
                        <tr><th class="px-5 py-2">Title</th><th class="px-5 py-2">Attempts</th></tr>
                    </thead>
                    <tbody>
                        @foreach($recentExamsTable as $e)
                            <tr class="border-b">
                                <td class="px-5 py-2 font-medium">{{ $e->title }}</td>
                                <td class="px-5 py-2 text-muted">{{ $e->attempts_count }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-layouts.studyai>
