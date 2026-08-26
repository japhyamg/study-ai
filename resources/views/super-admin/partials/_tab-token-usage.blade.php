@php
    /** @var array|null $tokenSummary */
    /** @var \Illuminate\Support\Collection $byOperation */
    /** @var \Illuminate\Support\Collection $byDay */
    /** @var int $days */
@endphp
@if(!$tokenSummary)
    <div class="surface p-8 text-center text-faint">Loading…</div>
@else
<div class="space-y-6">
    {{-- Day selector + header --}}
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-ink">AI Token Usage</h2>
        <form method="GET" class="flex items-center gap-2">
            <input type="hidden" name="tab" value="token-usage">
            <select name="days" class="border rounded px-2 py-1 text-sm" onchange="this.form.submit()">
                <option value="7" {{ ($days ?? 30) == 7 ? 'selected' : '' }}>Last 7 days</option>
                <option value="30" {{ ($days ?? 30) == 30 ? 'selected' : '' }}>Last 30 days</option>
                <option value="90" {{ ($days ?? 30) == 90 ? 'selected' : '' }}>Last 90 days</option>
            </select>
        </form>
    </div>

    {{-- Summary cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="stat"><div class="text-sm text-muted">Total Tokens</div><div class="text-2xl font-bold text-ink mt-1">{{ number_format($tokenSummary['totalTokens']) }}</div></div>
        <div class="stat"><div class="text-sm text-muted">Total Cost</div><div class="text-2xl font-bold text-ink mt-1">${{ $tokenSummary['totalCost'] }}</div></div>
        <div class="stat"><div class="text-sm text-muted">Requests</div><div class="text-2xl font-bold text-ink mt-1">{{ number_format($tokenSummary['totalRequests']) }}</div></div>
        <div class="stat"><div class="text-sm text-muted">Avg/Request</div><div class="text-2xl font-bold text-ink mt-1">{{ number_format($tokenSummary['avgTokensPerRequest']) }}</div></div>
    </div>

    {{-- By operation --}}
    @if($byOperation->isNotEmpty())
    <div class="surface">
        <div class="px-5 py-3 border-b font-semibold text-ink">By Operation</div>
        <div class="table-wrap"><table class="w-full text-sm">
            <thead class="text-left text-muted border-b">
                <tr><th class="px-5 py-2">Operation</th><th class="px-5 py-2">Tokens</th><th class="px-5 py-2">Cost</th><th class="px-5 py-2">Requests</th></tr>
            </thead>
            <tbody>
                @foreach($byOperation as $op)
                    <tr class="border-b">
                        <td class="px-5 py-2">{{ $op->operation }}</td>
                        <td class="px-5 py-2">{{ number_format($op->tokens) }}</td>
                        <td class="px-5 py-2">${{ number_format($op->cost, 4) }}</td>
                        <td class="px-5 py-2">{{ $op->count }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
    </div>
    @endif

    {{-- By day --}}
    @if($byDay->isNotEmpty())
    <div class="surface">
        <div class="px-5 py-3 border-b font-semibold text-ink">By Day</div>
        <div class="table-wrap"><table class="w-full text-sm">
            <thead class="text-left text-muted border-b">
                <tr><th class="px-5 py-2">Date</th><th class="px-5 py-2">Tokens</th><th class="px-5 py-2">Cost</th><th class="px-5 py-2">Requests</th></tr>
            </thead>
            <tbody>
                @foreach($byDay as $day)
                    @php $row = $day; @endphp
                    <tr class="border-b">
                        <td class="px-5 py-2 text-muted">{{ \Carbon\Carbon::parse($row->date)->format('M j, Y') }}</td>
                        <td class="px-5 py-2">{{ number_format($row->tokens) }}</td>
                        <td class="px-5 py-2">${{ number_format($row->cost, 4) }}</td>
                        <td class="px-5 py-2">{{ $row->count }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
    </div>
    @else
    <div class="surface p-8 text-center text-faint">No token usage data in this period.</div>
    @endif
</div>
@endif
