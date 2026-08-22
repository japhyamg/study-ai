<x-layouts.studyai title="Analytics">
    <div class="section-title mb-4">School Analytics</div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <x-ui.stat label="Attempts" :value="$totalAttempts" />
        <x-ui.stat label="Avg score" :value="$avgScore . '%'" />
        <x-ui.stat label="Pass rate" :value="$passRate . '%'" />
        <x-ui.stat label="Tokens used" :value="number_format($tokenUsage)" />
    </div>

    <div class="surface p-5 mb-6">
        <div class="font-medium text-ink mb-4">Score distribution</div>
        <div class="flex items-end gap-3" style="height: 160px">
            @php $labels = ['0–19','20–39','40–59','60–79','80–100']; @endphp
            @foreach($buckets as $i => $b)
                <div class="flex-1 flex flex-col items-center justify-end h-full">
                    <div class="text-xs text-faint mb-1">{{ $b }}</div>
                    <div style="height: {{ round($b / $maxBucket * 100) }}%; width:100%; background:var(--accent); border-radius:2px 2px 0 0"></div>
                    <div class="text-[11px] text-faint mt-2">{{ $labels[$i] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="surface p-5">
        <div class="font-medium text-ink mb-3">Class performance</div>
        @if($classStats->isEmpty())
            <div class="empty">No classes yet.</div>
        @else
            <table class="table">
                <thead><tr><th>Class</th><th>Students</th><th>Exams</th><th>Attempts</th><th>Avg score</th></tr></thead>
                <tbody>
                @foreach($classStats as $c)
                    <tr>
                        <td class="font-medium">{{ $c['name'] }}</td>
                        <td>{{ $c['students'] }}</td>
                        <td>{{ $c['exams'] }}</td>
                        <td>{{ $c['attempts'] }}</td>
                        <td>{{ $c['avg'] !== null ? $c['avg'] . '%' : '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-layouts.studyai>
