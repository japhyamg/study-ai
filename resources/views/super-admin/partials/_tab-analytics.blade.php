@php
    /** @var array|null $analyticsStats */
    /** @var array $signupsTrend */
    /** @var array $topSchools */
@endphp
@if(!$analyticsStats)
    <div class="surface p-8 text-center text-faint">Loading…</div>
@else
<div class="space-y-6">
    {{-- Analytics stat cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        @foreach([
            ['totalTeachers', 'Teachers', 'bg-paper border border-line'],
            ['totalStudents', 'Students', 'bg-paper border border-line'],
            ['avgScore', 'Avg Score', 'bg-paper border border-line'],
            ['passRate', 'Pass Rate', 'bg-paper border border-line'],
        ] as [$key, $label, $bg])
            <div class="{{ $bg }}" style="padding:1.25rem">
                <div class="text-sm text-muted">{{ $label }}</div>
                <div class="text-3xl font-bold text-ink mt-1" style="font-family: var(--font-display)">
                    @if($key === 'avgScore'){{ $analyticsStats[$key] }}%
                    @elseif($key === 'passRate'){{ $analyticsStats[$key] }}%
                    @else{{ $analyticsStats[$key] }}
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Signup Trend (bar chart) --}}
    <div class="surface">
        <div class="px-5 py-3 border-b font-semibold text-ink">User Signups (Last 30 Days)</div>
        <div class="px-5 py-4">
            @php $maxCount = max(1, ...array_column($signupsTrend, 'count')); @endphp
            <div class="flex items-end gap-1 h-40">
                @foreach($signupsTrend as $day)
                    @php $height = max(4, ($day['count'] / $maxCount) * 100); @endphp
                    <div class="flex-1 flex flex-col items-center justify-end h-full">
                        <div class="text-xs text-faint mb-1">{{ $day['count'] }}</div>
                        <div class="w-full" style="height: {{ $height }}%; background: var(--accent); border-radius:2px 2px 0 0"></div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Top Schools --}}
    <div class="surface">
        <div class="px-5 py-3 border-b font-semibold text-ink">Most Active Schools</div>
        @if(empty($topSchools))
            <div class="px-5 py-12 text-center text-faint">No data yet</div>
        @else
            <table class="w-full text-sm">
                <thead class="text-left text-muted border-b">
                    <tr><th class="px-5 py-2">#</th><th class="px-5 py-2">School</th><th class="px-5 py-2 text-right">Attempts</th></tr>
                </thead>
                <tbody>
                    @foreach($topSchools as $i => $s)
                        <tr class="border-b">
                            <td class="px-5 py-2 text-muted">{{ $i + 1 }}</td>
                            <td class="px-5 py-2 font-medium">{{ $s['schoolName'] }}</td>
                            <td class="px-5 py-2 text-right text-muted">{{ $s['attempts'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endif
