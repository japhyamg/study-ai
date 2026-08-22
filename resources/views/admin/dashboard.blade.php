<x-layouts.studyai title="Dashboard" subtitle="{{ $school->name ?? 'School' }}">
    {{-- Stat cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="stat">
            <div class="stat-label">Teachers</div>
            <div class="stat-value">{{ $stats['teachers'] ?? 0 }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Students</div>
            <div class="stat-value">{{ $stats['students'] ?? 0 }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Classes</div>
            <div class="stat-value">{{ $stats['classes'] ?? 0 }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Avg score</div>
            <div class="stat-value">{{ $stats['avgScore'] ?? 0 }}%</div>
        </div>
    </div>

    {{-- Recent Activity + Recent Exams (two columns) --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Recent Activity --}}
        <div class="surface">
            <div class="px-5 py-3 border-b font-semibold text-ink">Recent activity</div>
            @if($recentActivity->isEmpty())
                <div class="px-5 py-8 text-center text-faint">No recent activity.</div>
            @else
                <ul class="divide-y">
                    @foreach($recentActivity as $a)
                        <li class="px-5 py-2.5 text-sm flex items-center justify-between gap-3">
                            <span class="text-muted min-w-0">
                                @if($a['type'] === 'join')
                                    <span class="text-ink font-medium">{{ $a['user'] }}</span> joined the school
                                @else
                                    {{ $a['text'] }}
                                @endif
                            </span>
                            <span class="text-xs text-faint flex-none">{{ $a['time'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Recent Exams --}}
        <div class="surface">
            <div class="px-5 py-3 border-b font-semibold text-ink">Recent exams</div>
            @if($recentExamsTable->isEmpty())
                <div class="px-5 py-8 text-center text-faint">No exams yet.</div>
            @else
                <div class="table-wrap"><table class="table">
                    <thead>
                        <tr><th>Title</th><th class="text-right">Attempts</th></tr>
                    </thead>
                    <tbody>
                        @foreach($recentExamsTable as $e)
                            <tr>
                                <td class="px-5 py-2.5 font-medium">{{ $e->title }}</td>
                                <td class="px-5 py-2.5 text-muted text-right">{{ $e->attempts_count }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table></div>
            @endif
        </div>
    </div>
</x-layouts.studyai>
