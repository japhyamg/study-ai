@php
    /** @var array|null $usageTeachersSummary */
    /** @var array $schoolsData */
    /** @var int $days */
@endphp
@if(!$usageTeachersSummary)
    <div class="surface p-8 text-center text-faint">Loading…</div>
@else
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-ink">Usage &amp; Teachers</h2>
        <form method="GET" class="flex items-center gap-2">
            <input type="hidden" name="tab" value="usage-teachers">
            <select name="days" class="border rounded px-2 py-1 text-sm" onchange="this.form.submit()">
                <option value="7" {{ ($days ?? 30) == 7 ? 'selected' : '' }}>Last 7 days</option>
                <option value="30" {{ ($days ?? 30) == 30 ? 'selected' : '' }}>Last 30 days</option>
                <option value="90" {{ ($days ?? 30) == 90 ? 'selected' : '' }}>Last 90 days</option>
            </select>
        </form>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-paper border border-line" style="border-radius:3px; padding:1.25rem"><div class="text-sm text-muted">Total Tokens</div><div class="text-2xl font-bold text-ink mt-1">{{ number_format($usageTeachersSummary['totalTokens']) }}</div></div>
        <div class="bg-paper border border-line" style="border-radius:3px; padding:1.25rem"><div class="text-sm text-muted">Total Cost</div><div class="text-2xl font-bold text-ink mt-1">${{ $usageTeachersSummary['totalCost'] }}</div></div>
        <div class="bg-paper border border-line" style="border-radius:3px; padding:1.25rem"><div class="text-sm text-muted">Requests</div><div class="text-2xl font-bold text-ink mt-1">{{ number_format($usageTeachersSummary['totalRequests']) }}</div></div>
        <div class="bg-paper border border-line" style="border-radius:3px; padding:1.25rem"><div class="text-sm text-muted">Active Schools</div><div class="text-2xl font-bold text-ink mt-1">{{ $usageTeachersSummary['schoolCount'] }}</div></div>
    </div>

    @if(empty($schoolsData))
        <div class="surface p-8 text-center text-faint">No usage recorded in this period.</div>
    @else
    <div class="surface">
        <div class="px-5 py-3 border-b font-semibold text-ink">Usage by School</div>
        <table class="w-full text-sm">
            <thead class="text-left text-muted border-b">
                <tr><th class="px-5 py-2">School</th><th class="px-5 py-2">Tokens</th><th class="px-5 py-2">Cost</th><th class="px-5 py-2">Requests</th><th class="px-5 py-2">Teachers</th></tr>
            </thead>
            <tbody>
                @foreach($schoolsData as $s)
                    <tr class="border-b">
                        <td class="px-5 py-2 font-medium">{{ $s['schoolName'] }}</td>
                        <td class="px-5 py-2">{{ number_format($s['tokens']) }}</td>
                        <td class="px-5 py-2">${{ number_format($s['cost'], 4) }}</td>
                        <td class="px-5 py-2">{{ $s['requests'] }}</td>
                        <td class="px-5 py-2 text-muted">{{ count($s['teachers']) }}</td>
                    </tr>
                    @if(!empty($s['teachers']))
                        @foreach($s['teachers'] as $t)
                            <tr class="border-b bg-paper-sunk/30">
                                <td class="px-5 py-2 pl-8 text-sm">{{ $t['name'] }}<div class="text-xs text-faint">{{ $t['email'] }}</div></td>
                                <td colspan="3" class="px-5 py-2 text-xs text-muted">Monthly tokens: {{ number_format($t['usedThisMonth']) }}</td>
                                <td class="px-5 py-2 text-xs text-muted capitalize">{{ $t['role'] }}</td>
                            </tr>
                        @endforeach
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@endif
